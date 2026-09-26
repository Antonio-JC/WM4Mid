<?php
declare(strict_types=1);

require_once __DIR__ . '/CrudInterface.php';

abstract class BaseModel implements CrudInterface
{
    protected PDO $db;
    protected string $table;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    abstract public function create(array $data): int;
    abstract public function read(int $id): ?array;
    abstract public function update(int $id, array $data): bool;
    abstract public function all(array $filters = []): array;

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare("DELETE FROM {$this->table} WHERE id = :id");
        return $stmt->execute(['id' => $id]);
    }

    protected function build_sort_and_limit(array $filters, array $allowed_columns, string $default_column): array
    {
        $column = in_array($filters['sort'] ?? '', $allowed_columns, true)
            ? $filters['sort']
            : $default_column;

        $direction = strtoupper($filters['direction'] ?? 'ASC') === 'DESC' ? 'DESC' : 'ASC';

        $per_page = isset($filters['per_page']) ? max(1, min(100, (int) $filters['per_page'])) : 25;
        $page     = isset($filters['page']) ? max(1, (int) $filters['page']) : 1;
        $offset   = ($page - 1) * $per_page;

        $sql = " ORDER BY $column $direction LIMIT $per_page OFFSET $offset";

        return [
            'sql'       => $sql,
            'column'    => $column,
            'direction' => $direction,
            'limit'     => $per_page,
            'offset'    => $offset
        ];
    }
}