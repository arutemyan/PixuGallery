<?php

declare(strict_types=1);

/**
 * 管理画面の認証チェック
 *
 * すべての管理画面APIで使用
 */

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../src/Security/SecurityUtil.php';
$config = \App\Config\ConfigManager::getInstance()->getConfig();

use App\Security\CsrfProtection;

// セッション開始（Session サービスを必須とする - 無ければエラーにする）
\App\Services\Session::start();

// 認証チェック（Session を前提にする）
$sess = \App\Services\Session::getInstance();
if ($sess->get('admin_logged_in') !== true) {
    http_response_code(401);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'error' => '認証が必要です。ログインしてください。'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}
