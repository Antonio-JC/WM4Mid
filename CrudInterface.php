<?php
declare(strict_types=1);

interface CrudInterface
{
    public function create(array $data): int;
    public function read(int $id): ?array;
    public function update(int $id, array $data): bool;
    public function delete(int $id): bool;
    public function all(array $filters = []): array;
}