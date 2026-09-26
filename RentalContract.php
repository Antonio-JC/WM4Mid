<?php
declare(strict_types=1);

require_once __DIR__ . '/BaseModel.php';
require_once __DIR__ . '/ValidationException.php';
require_once __DIR__ . '/InventoryManager.php';

class RentalContract extends BaseModel
{
    public const STATUS_ACTIVE   = 'Active';
    public const STATUS_RETURNED = 'Returned';
    public const STATUS_OVERDUE  = 'Overdue';

    protected string $table = 'rental_contracts';

    private InventoryManager $inventory_manager;

    public function __construct(PDO $db, ?InventoryManager $inventory_manager = null)
    {
        parent::__construct($db);
        $this->inventory_manager = $inventory_manager ?? new InventoryManager($db);
    }

    public function create(array $data): int
    {
        $this->validate_new_rental($data);

        $checkout_date = $data['checkout_date'] ?? date('Y-m-d');
        $inventory_ids = array_values(array_unique(array_map('intval', $data['inventory_ids'])));
        
        $this->db->beginTransaction();

        try {
            $stmt = $this->db->prepare(
                'INSERT INTO rental_contracts (customer_id, checkout_date, due_date, deposit_amount, status)
                 VALUES (:customer_id, :checkout_date, :due_date, :deposit_amount, :status)'
            );
            $stmt->execute([
                'customer_id'    => $data['customer_id'],
                'checkout_date'  => $checkout_date,
                'due_date'       => $data['due_date'],
                'deposit_amount' => $data['deposit_amount'],
                'status'         => self::STATUS_ACTIVE,
            ]);

            $contract_id = (int) $this->db->lastInsertId();

            $stmt_item = $this->db->prepare(
                'INSERT INTO contract_items (contract_id, inventory_id, daily_rate_at_checkout, returned)
                 VALUES (:contract_id, :inventory_id, (SELECT daily_rate FROM inventory WHERE id = :inv_id LIMIT 1), 0)'
            );

            foreach ($inventory_ids as $id) {
                $stmt_item->execute([
                    'contract_id'  => $contract_id,
                    'inventory_id' => $id,
                    'inv_id'       => $id,
                ]);
                $this->inventory_manager->mark_rented($id);
            }

            $this->db->commit();
            return $contract_id;
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function read(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM rental_contracts WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $contract = $stmt->fetch();

        if (!$contract) {
            return null;
        }

        $stmt_items = $this->db->prepare('SELECT * FROM contract_items WHERE contract_id = :id');
        $stmt_items->execute(['id' => $id]);
        $contract['items'] = $stmt_items->fetchAll();

        return $contract;
    }

    public function update(int $id, array $data): bool
    {
        if (empty($data)) {
            return false;
        }

        $fields = [];
        $params = ['id' => $id];

        foreach (['due_date', 'deposit_amount', 'status'] as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = "$field = :$field";
                $params[$field] = $data[$field];
            }
        }

        if (empty($fields)) {
            return false;
        }

        $sql = 'UPDATE rental_contracts SET ' . implode(', ', $fields) . ' WHERE id = :id';
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }

    public function all(array $filters = []): array
    {
        $sql = 'SELECT rc.*, c.name as customer_name FROM rental_contracts rc 
                LEFT JOIN customers c ON rc.customer_id = c.id WHERE 1=1';
        $params = [];

        if (!empty($filters['status'])) {
            $sql .= ' AND rc.status = :status';
            $params['status'] = $filters['status'];
        }

        if (!empty($filters['search'])) {
            $sql .= ' AND c.name LIKE :search';
            $params['search'] = '%' . $filters['search'] . '%';
        }

        $sort_and_limit = $this->build_sort_and_limit($filters, ['due_date', 'checkout_date', 'customer_name'], 'due_date');
        $sql .= $sort_and_limit['sql'];

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public function process_return(int $contract_id, ?array $inventory_ids = null): float
    {
        $contract = $this->read($contract_id);
        if (!$contract || $contract['status'] === self::STATUS_RETURNED) {
            throw new ValidationException('Invalid or already closed contract.');
        }

        $late_fee = $this->calculate_late_fee($contract_id);

        $this->db->beginTransaction();

        try {
            $items_to_return = $inventory_ids ?? array_column(
                array_filter($contract['items'], fn($item) => $item['returned'] == 0), 
                'inventory_id'
            );

            $stmt_update_item = $this->db->prepare(
                'UPDATE contract_items SET returned = 1 WHERE contract_id = :cid AND inventory_id = :iid'
            );

            foreach ($items_to_return as $inv_id) {
                $stmt_update_item->execute(['cid' => $contract_id, 'iid' => $inv_id]);
                
                if (method_exists($this->inventory_manager, 'mark_available')) {
                    $this->inventory_manager->mark_available((int)$inv_id);
                }
            }

            $stmt_check_pending = $this->db->prepare(
                'SELECT COUNT(*) FROM contract_items WHERE contract_id = :cid AND returned = 0'
            );
            $stmt_check_pending->execute(['cid' => $contract_id]);
            
            if ((int) $stmt_check_pending->fetchColumn() === 0) {
                $stmt_close = $this->db->prepare(
                    'UPDATE rental_contracts SET status = :status, return_date = :return_date WHERE id = :id'
                );
                $stmt_close->execute([
                    'status' => self::STATUS_RETURNED, 
                    'return_date' => date('Y-m-d'), 
                    'id' => $contract_id
                ]);
            }

            $this->db->commit();
            return $late_fee;
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function calculate_late_fee(int $contract_id, DateTimeImmutable $as_of = null): float
    {
        $as_of = $as_of ?? new DateTimeImmutable();
        
        $stmt = $this->db->prepare(
            'SELECT rc.due_date, rc.return_date, ci.daily_rate_at_checkout 
             FROM rental_contracts rc 
             JOIN contract_items ci ON rc.id = ci.contract_id 
             WHERE rc.id = :id AND ci.returned = 0'
        );
        $stmt->execute(['id' => $contract_id]);
        $rows = $stmt->fetchAll();

        if (empty($rows)) {
            return 0.0;
        }

        $due_date = new DateTimeImmutable($rows[0]['due_date']);
        
        $reference_date = $rows[0]['return_date'] !== null
            ? new DateTimeImmutable($rows[0]['return_date'])
            : $as_of;

        if ($reference_date <= $due_date) {
            return 0.0;
        }

        $days_late = (int) $due_date->diff($reference_date)->days;

        $total = 0.0;
        foreach ($rows as $row) {
            $total += (float) $row['daily_rate_at_checkout'] * $days_late;
        }

        return round($total, 2);
    }

    private function validate_new_rental(array $data): void
    {
        foreach (['customer_id', 'due_date', 'inventory_ids'] as $field) {
            if (empty($data[$field])) {
                throw new ValidationException("Field '$field' is required.");
            }
        }

        if (!is_array($data['inventory_ids']) || count($data['inventory_ids']) === 0) {
            throw new ValidationException('At least one inventory_id must be selected.');
        }

        if (!isset($data['deposit_amount']) || !is_numeric($data['deposit_amount']) || $data['deposit_amount'] < 0) {
            throw new ValidationException("Field 'deposit_amount' must be a non-negative number.");
        }

        $customer_check = $this->db->prepare('SELECT COUNT(*) FROM customers WHERE id = :id');
        $customer_check->execute(['id' => $data['customer_id']]);
        if ((int) $customer_check->fetchColumn() === 0) {
             throw new ValidationException("Invalid customer_id.");
        }
    }
}