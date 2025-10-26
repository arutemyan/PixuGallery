<?php

declare(strict_types=1);

namespace App\Utils;

/**
 * パスヘルパークラス
 *
 * アプリケーション内のパスを管理
 */
class PathHelper
{
    private static ?array $config = null;

    /**
     * 設定を読み込み
     */
    private static function loadConfig(): void
    {
        if (self::$config === null) {
            $configPath = __DIR__ . '/../../config/config.php';
            if (file_exists($configPath)) {
                self::$config = require $configPath;
            } else {
                self::$config = ['admin' => ['path' => 'admin']];
            }
        }
    }

    /**
     * 管理画面のディレクトリ名を取得
     *
     * @return string 管理画面のディレクトリ名（例: 'admin', 'fehihfnFG__'）
     */
    public static function getAdminPath(): string
    {
        self::loadConfig();
        return self::$config['admin']['path'] ?? 'admin';
    }

    /**
     * 管理画面のURLパスを取得
     *
     * @param string $subPath サブパス（例: 'login.php', 'api/upload.php'）
     * @return string 管理画面のURLパス（例: '/admin/login.php'）
     */
    public static function getAdminUrl(string $subPath = ''): string
    {
        $adminPath = self::getAdminPath();
        $subPath = ltrim($subPath, '/');

        if ($subPath === '') {
            return '/' . $adminPath;
        }

        return '/' . $adminPath . '/' . $subPath;
    }

    /**
     * 管理画面の物理パスを取得
     *
     * @param string $subPath サブパス
     * @return string 管理画面の物理パス
     */
    public static function getAdminDir(string $subPath = ''): string
    {
        $adminPath = self::getAdminPath();
        $publicDir = __DIR__ . '/../../public';
        $subPath = ltrim($subPath, '/');

        if ($subPath === '') {
            return $publicDir . '/' . $adminPath;
        }

        return $publicDir . '/' . $adminPath . '/' . $subPath;
    }

    /**
     * 現在のURLが管理画面かどうかを判定
     *
     * @return bool 管理画面の場合true
     */
    public static function isAdminPath(): bool
    {
        $adminPath = self::getAdminPath();
        $requestUri = $_SERVER['REQUEST_URI'] ?? '';

        return strpos($requestUri, '/' . $adminPath . '/') === 0
            || strpos($requestUri, '/' . $adminPath) === 0;
    }
}
