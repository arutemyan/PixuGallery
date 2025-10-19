<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../src/Security/SecurityUtil.php';

use App\Models\Setting;
use App\Security\CsrfProtection;

initSecureSession();

// 認証チェック
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

$settingModel = new Setting();

try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        // 設定取得
        $settings = $settingModel->getAll();
        echo json_encode(['success' => true, 'settings' => $settings]);

    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // 設定更新
        if (!CsrfProtection::validatePost()) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'CSRF token validation failed']);
            exit;
        }

        $showViewCount = $_POST['show_view_count'] ?? '0';
        $settingModel->set('show_view_count', $showViewCount);

        echo json_encode(['success' => true, 'message' => '設定が保存されました']);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Internal server error']);
    error_log('Settings API Error: ' . $e->getMessage());
}
