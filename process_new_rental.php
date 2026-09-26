<?php
declare(strict_types=1);

header('Content-Type: application/json');

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/RentalContract.php';
require_once __DIR__ . '/ValidationException.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed. Use POST.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

try {
    $db       = Database::connection();
    $contract = new RentalContract($db);

    $contract_id = $contract->create([
        'customer_id'    => $input['customer_id']    ?? null,
        'checkout_date'  => $input['checkout_date']   ?? date('Y-m-d'),
        'due_date'       => $input['due_date']        ?? null,
        'deposit_amount' => $input['deposit_amount']  ?? null,
        'inventory_ids'  => $input['inventory_ids']   ?? [],
    ]);

    http_response_code(201);
    echo json_encode([
        'success'     => true,
        'contract_id' => $contract_id,
        'contract'    => $contract->read($contract_id),
    ]);
} catch (ValidationException $e) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error']);
}