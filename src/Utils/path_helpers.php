<?php

declare(strict_types=1);

/**
 * パスヘルパー関数
 *
 * グローバルスコープで使用できる便利な関数
 */

use App\Utils\PathHelper;

require_once __DIR__ . '/PathHelper.php';

/**
 * 管理画面のURLパスを取得
 *
 * @param string $subPath サブパス（例: 'login.php', 'api/upload.php'）
 * @return string 管理画面のURLパス（例: '/admin/login.php'）
 */
function admin_url(string $subPath = ''): string
{
    return PathHelper::getAdminUrl($subPath);
}

/**
 * 管理画面のディレクトリ名を取得
 *
 * @return string 管理画面のディレクトリ名（例: 'admin', 'fehihfnFG__'）
 */
function admin_path(): string
{
    return PathHelper::getAdminPath();
}

/**
 * 現在のURLが管理画面かどうかを判定
 *
 * @return bool 管理画面の場合true
 */
function is_admin(): bool
{
    return PathHelper::isAdminPath();
}
