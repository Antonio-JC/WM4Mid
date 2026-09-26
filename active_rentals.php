<?php
declare(strict_types=1);

header('Content-Type: application/json');

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/RentalContract.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed. Use GET.']);
    exit;
}

$filters = [
    'status'    => $_GET['status']    ?? null,  // 'Active' | 'Overdue' | 'Returned'
    'search'    => $_GET['search']    ?? null,  // matched against customer name
    'sort'      => $_GET['sort']      ?? null,  // due_date | checkout_date | customer_name | item_name
    'direction' => $_GET['direction'] ?? null,  // ASC | DESC
    'page'      => $_GET['page']      ?? null,
    'per_page'  => $_GET['per_page']  ?? null,
];
$filters = array_filter($filters, fn ($v) => $v !== null && $v !== '');

try {
    $db       = Database::connection();
    $contract = new RentalContract($db);

    echo json_encode([
        'success' => true,
        'filters' => $filters,
        'results' => $contract->all($filters),
    ]);
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error fetching rentals.']);
}
