<?php
/**
 * Admin Configuration API
 * 
 * Provides configuration values to admin interface via API
 * This eliminates the need for inline scripts with configuration data
 * Part of CSP unsafe-inline removal strategy
 */

declare(strict_types=1);

require_once __DIR__ . '/../../../init.php';

use App\Controllers\AdminControllerBase;
use App\Security\CsrfProtection;
use App\Utils\PathHelper;

// 認証チェック
AdminControllerBase::ensureAuthenticated(false);

// CSRF トークンを生成
$csrfToken = CsrfProtection::generateToken();

// 管理画面パスを取得
$adminPath = PathHelper::getAdminPath();

// ユーザー名を取得
$username = 'Admin';
try {
    if (class_exists('\App\\Services\\Session')) {
        $username = \App\Services\Session::getInstance()->get('admin_username', $username);
    } else {
        $username = $_SESSION['admin_username'] ?? $username;
    }
} catch (Throwable $e) {
    $username = $_SESSION['admin_username'] ?? $username;
}

// 設定データを JSON で返す
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

echo json_encode([
    'csrfToken' => $csrfToken,
    'adminPath' => $adminPath,
    'username' => $username,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
