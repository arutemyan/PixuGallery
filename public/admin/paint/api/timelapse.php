<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../../vendor/autoload.php';
require_once __DIR__ . '/../../../../src/Security/SecurityUtil.php';

use App\Database\Connection;
use App\Services\TimelapseService;

initSecureSession();

// Admin check - support both session formats
$userId = null;
if (!empty($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    $userId = $_SESSION['admin_user_id'] ?? null;
} elseif (!empty($_SESSION['admin']) && is_array($_SESSION['admin'])) {
    $userId = $_SESSION['admin']['id'] ?? null;
}

if ($userId === null) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

header('Content-Type: application/json');

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$publicRoot = realpath(__DIR__ . '/../../..');  // public ディレクトリ

$result = TimelapseService::getTimelapseData($id, $publicRoot);

if (!$result['success']) {
    $statusCode = 400;
    if (strpos($result['error'], 'not found') !== false) {
        $statusCode = 404;
    } else if (strpos($result['error'], 'Server error') !== false) {
        $statusCode = 500;
    }
    http_response_code($statusCode);
}

echo json_encode($result, JSON_UNESCAPED_UNICODE);
