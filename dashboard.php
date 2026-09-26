<?php
declare(strict_types=1);

header('Content-Type: application/json');

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/DashboardService.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed. Use GET.']);
    exit;
}

try {
    $db        = Database::connection();
    $dashboard = new DashboardService($db);

    echo json_encode(['success' => true, 'summary' => $dashboard->summary()]);
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error building dashboard.']);
}
