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

if (empty($input['contract_id'])) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => "Field 'contract_id' is required."]);
    exit;
}

$inventory_ids = $input['inventory_ids'] ?? null;

try {
    $db       = Database::connection();
    $contract = new RentalContract($db);

    $late_fee = $contract->process_return((int) $input['contract_id'], $inventory_ids);

    echo json_encode([
        'success'     => true,
        'contract_id' => (int) $input['contract_id'],
        'late_fee'    => $late_fee,
        'contract'    => $contract->read((int) $input['contract_id']),
    ]);
} catch (ValidationException $e) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error']);
}