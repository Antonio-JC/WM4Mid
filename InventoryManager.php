<?php
declare(strict_types=1);

require_once __DIR__ . '/BaseModel.php';
require_once __DIR__ . '/ValidationException.php';

class InventoryManager extends BaseModel
{
    public const STATUS_AVAILABLE   = 'Available';
    public const STATUS_RENTED      = 'Rented';
    public const STATUS_MAINTENANCE = 'Maintenance';

    private const VALID_STATUSES = [
        self::STATUS_AVAILABLE,
        self::STATUS_RENTED,
        self::STATUS_MAINTENANCE,
    ];

    protected string $table = 'inventory';

    public function create(array $data): int
    {
        $this->validate($data);

        $stmt = $this->db->prepare(
            'INSERT INTO inventory (item_name, daily_rate, status, serial_number)
             VALUES (:item_name, :daily_rate, :status, :serial_number)'
        );
        $stmt->execute([
            'item_name'     => trim($data['item_name']),
            'daily_rate'    => $data['daily_rate'],
            'status'        => $data['status'] ?? self::STATUS_AVAILABLE,
            'serial_number' => trim($data['serial_number']),
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function read(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM inventory WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function update(int $id, array $data): bool
    {
        if (empty($data)) {
            return false;
        }

        if (isset($data['status'])) {
            $this->assert_valid_status($data['status']);
        }

        $fields = [];
        $params = ['id' => $id];

        foreach (['item_name', 'daily_rate', 'status', 'serial_number'] as $field) {
            if (array_key_exists($field, $data)) {
                $fields[]       = "$field = :$field";
                $params[$field] = $data[$field];
            }
        }

        if (empty($fields)) {
            return false;
        }

        $sql  = 'UPDATE inventory SET ' . implode(', ', $fields) . ' WHERE id = :id';
        $stmt = $this->db->prepare($sql);
        
        return $stmt->execute($params);
    }

    public function all(array $filters = []): array
    {
        $sql    = 'SELECT * FROM inventory';
        $params = [];
        $where  = [];

        if (!empty($filters['status'])) {
            $where[]          = 'status = :status';
            $params['status'] = $filters['status'];
        }

        if (!empty($filters['search'])) {
        $where[] = '(item_name LIKE :search1 OR serial_number LIKE :search2)';
        $term = '%' . $filters['search'] . '%';
        $params['search1'] = $term;
        $params['search2'] = $term;
        }

        if (!empty($where)) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $sort_and_limit = $this->build_sort_and_limit($filters, ['item_name', 'daily_rate', 'status'], 'item_name');
        $sql .= $sort_and_limit['sql'];

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public function mark_rented(int $inventory_id): bool
    {
        return $this->set_status($inventory_id, self::STATUS_RENTED);
    }

    public function mark_available(int $inventory_id): bool
    {
        return $this->set_status($inventory_id, self::STATUS_AVAILABLE);
    }

    public function mark_maintenance(int $inventory_id): bool
    {
        return $this->set_status($inventory_id, self::STATUS_MAINTENANCE);
    }

    private function set_status(int $inventory_id, string $status): bool
    {
        $this->assert_valid_status($status);

        $stmt = $this->db->prepare('UPDATE inventory SET status = :status WHERE id = :id');
        return $stmt->execute(['status' => $status, 'id' => $inventory_id]);
    }

    private function assert_valid_status(string $status): void
    {
        if (!in_array($status, self::VALID_STATUSES, true)) {
            throw new ValidationException("Invalid inventory status: $status");
        }
    }

    private function validate(array $data): void
    {
        if (empty(trim((string) ($data['item_name'] ?? '')))) {
            throw new ValidationException("Field 'item_name' is required.");
        }
        if (empty(trim((string) ($data['serial_number'] ?? '')))) {
            throw new ValidationException("Field 'serial_number' is required.");
        }
        if (!isset($data['daily_rate']) || !is_numeric($data['daily_rate']) || $data['daily_rate'] < 0) {
            throw new ValidationException("Field 'daily_rate' must be a non-negative number.");
        }
        if (isset($data['status'])) {
            $this->assert_valid_status($data['status']);
        }
    }
}
