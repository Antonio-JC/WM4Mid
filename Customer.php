<?php
declare(strict_types=1);

require_once __DIR__ . '/BaseModel.php';
require_once __DIR__ . '/ValidationException.php';

class Customer extends BaseModel
{
    protected string $table = 'customers';

    public function create(array $data): int
    {
        $this->validate_data($data);

        $stmt = $this->db->prepare(
            'INSERT INTO customers (name, id_number, phone) VALUES (:name, :id_number, :phone)'
        );
        $stmt->execute([
            'name'      => trim($data['name']),
            'id_number' => trim($data['id_number']),
            'phone'     => trim($data['phone']),
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function read(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM customers WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function update(int $id, array $data): bool
    {
        if (empty($data)) {
            return false;
        }

        $fields = [];
        $params = ['id' => $id];

        foreach (['name', 'id_number', 'phone'] as $field) {
            if (array_key_exists($field, $data)) {
                $fields[]       = "$field = :$field";
                $params[$field] = trim((string) $data[$field]);
            }
        }

        if (empty($fields)) {
            return false;
        }

        $sql  = 'UPDATE customers SET ' . implode(', ', $fields) . ' WHERE id = :id';
        $stmt = $this->db->prepare($sql);
        
        return $stmt->execute($params);
    }

    public function all(array $filters = []): array
{
    $sql    = 'SELECT * FROM customers';
    $params = [];

    if (!empty($filters['search'])) {
        $sql .= ' WHERE name LIKE :search1 OR phone LIKE :search2 OR id_number LIKE :search3';
        $term = '%' . $filters['search'] . '%';
        $params['search1'] = $term;
        $params['search2'] = $term;
        $params['search3'] = $term;
    }

    $sort_and_limit = $this->build_sort_and_limit($filters, ['name', 'id_number', 'created_at'], 'name');
    $sql           .= $sort_and_limit['sql'];

    $stmt = $this->db->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll();
}

    private function validate_data(array $data): void
    {
        foreach (['name', 'id_number', 'phone'] as $field) {
            if (empty(trim((string) ($data[$field] ?? '')))) {
                throw new ValidationException("Field '$field' is required.");
            }
        }
    }
}
