<?php

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../src/Security/SecurityUtil.php';
$config = \App\Config\ConfigManager::getInstance()->getConfig();

use App\Security\CsrfProtection;
use App\Utils\PathHelper;

// セッション開始
\App\Services\Session::start();

// POSTリクエストのみ許可（CSRF対策）
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Location: ' . PathHelper::getAdminUrl('index.php'));
    exit;
}

// CSRFトークン検証
if (!CsrfProtection::validatePost() && !CsrfProtection::validateHeader()) {
    http_response_code(403);
    logSecurityEvent('CSRF token validation failed on logout', ['ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown']);
    header('Location: ' . PathHelper::getAdminUrl('index.php'));
    exit;
}

$sess = \App\Services\Session::getInstance();
$sess->destroy();
// start a fresh session and regenerate id
\App\Services\Session::start();
\App\Services\Session::getInstance()->regenerate(true);

// セキュリティログを記録
logSecurityEvent('Admin logout', ['ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown']);

// ログインページへリダイレクト
header('Location: ' . PathHelper::getAdminUrl('login.php'));
exit;
