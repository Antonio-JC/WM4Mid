<?php
declare(strict_types=1);

header('Content-Type: application/json');

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/InventoryManager.php';
require_once __DIR__ . '/ValidationException.php';

$db        = Database::connection();
$inventory = new InventoryManager($db);
$method    = $_SERVER['REQUEST_METHOD'];
$id        = isset($_GET['id']) ? (int) $_GET['id'] : null;
$input     = json_decode(file_get_contents('php://input'), true) ?? [];

try {
    switch ($method) {
        case 'GET':
            if ($id) {
                $row = $inventory->read($id);
                $row ? print(json_encode(['success' => true, 'item' => $row]))
                     : (function () { http_response_code(404); echo json_encode(['success' => false, 'error' => 'Not found']); })();
            } else {
                $filters = array_filter([
                    'status'    => $_GET['status']    ?? null,
                    'search'    => $_GET['search']    ?? null,
                    'sort'      => $_GET['sort']      ?? null,
                    'direction' => $_GET['direction'] ?? null,
                    'page'      => $_GET['page']      ?? null,
                    'per_page'  => $_GET['per_page']  ?? null,
                ]);
                echo json_encode(['success' => true, 'results' => $inventory->all($filters)]);
            }
            break;

        case 'POST':
            $new_id = $inventory->create($input);
            http_response_code(201);
            echo json_encode(['success' => true, 'id' => $new_id, 'item' => $inventory->read($new_id)]);
            break;

        case 'PUT':
        case 'PATCH':
            if (!$id) {
                throw new ValidationException("Query param 'id' is required.");
            }
            $inventory->update($id, $input);
            echo json_encode(['success' => true, 'item' => $inventory->read($id)]);
            break;

        case 'DELETE':
            if (!$id) {
                throw new ValidationException("Query param 'id' is required.");
            }
            $inventory->delete($id);
            echo json_encode(['success' => true]);
            break;

        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed.']);
    }
} catch (ValidationException $e) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error']);
}