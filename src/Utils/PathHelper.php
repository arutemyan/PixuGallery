<?php

declare(strict_types=1);

namespace App\Utils;

/**
 * PathHelper
 *
 * Centralized helper to resolve application filesystem paths defined in config.
 * Use these helpers throughout the codebase to avoid hard-coded `data/` paths.
 */
class PathHelper
{
    private static ?array $config = null;

    private static function loadConfig(): void
    {
        if (self::$config === null) {
            self::$config = \App\Config\ConfigManager::getInstance()->getConfig();
            if (!is_array(self::$config)) {
                self::$config = [];
            }
        }
    }

    private static function getConfig(): array
    {
        self::loadConfig();
        return self::$config ?? [];
    }

    public static function getDataDir(): string
    {
        $cfg = self::getConfig();
        return $cfg['paths']['data_dir'] ?? (__DIR__ . '/../../data');
    }

    public static function getCacheDir(): string
    {
        $cfg = self::getConfig();
        return $cfg['paths']['cache'] ?? self::getDataDir() . '/cache';
    }

    public static function getLogDir(): string
    {
        $cfg = self::getConfig();
        return $cfg['paths']['log'] ?? self::getDataDir() . '/log';
    }

    public static function getRateLimitDir(): string
    {
        $cfg = self::getConfig();
        return $cfg['paths']['rate_limits'] ?? self::getDataDir() . '/rate-limits';
    }

    public static function getCountersPath(): string
    {
        $cfg = self::getConfig();
        return $cfg['paths']['counters'] ?? self::getDataDir() . '/counters.db';
    }

    public static function getUploadsDir(): string
    {
        $cfg = self::getConfig();
        return $cfg['paths']['uploads'] ?? __DIR__ . '/../../uploads';
    }

    /**
     * 公開側の uploads URL を返す（例: '/uploads'）
     */
    public static function getUploadsUrl(string $subPath = ''): string
    {
        $cfg = self::getConfig();
        $base = $cfg['paths']['uploads_url'] ?? '/uploads';
        $subPath = ltrim($subPath, '/');
        if ($subPath === '') return $base;
        return rtrim($base, '/') . '/' . $subPath;
    }

    /**
     * アップロード済みのプレースホルダー画像のフルURLを返す
     * 設定 `paths.uploads_placeholder` があればそれを使用し、なければデフォルトの 'thumbs/placeholder.webp' を使用する。
     */
    public static function getUploadsPlaceholderUrl(): string
    {
        $cfg = self::getConfig();
        $raw = trim((string)($cfg['paths']['uploads_placeholder'] ?? '/uploads/thumbs/placeholder.webp'));

        // Empty -> fallback to default
        if ($raw === '') {
            return '/uploads/thumbs/placeholder.webp';
        }

        // If absolute http(s) URL and valid, allow
        if (preg_match('#^https?://#i', $raw)) {
            if (filter_var($raw, FILTER_VALIDATE_URL) !== false) {
                return $raw;
            }
            return '/uploads/thumbs/placeholder.webp';
        }

        // Allow root-relative paths only (must begin with '/')
        if (str_starts_with($raw, '/')) {
            return $raw;
        }

        // Anything else: treat as invalid for simplicity — return default
        return '/uploads/thumbs/placeholder.webp';
    }

    public static function ensureDir(string $dir, int $mode = 0755): void
    {
        if (!is_dir($dir)) {
            @mkdir($dir, $mode, true);
        }
    }

    // Admin helpers (kept here for convenience)
    public static function getAdminPath(): string
    {
        $cfg = self::getConfig();
        return $cfg['admin']['path'] ?? 'admin';
    }

    public static function getAdminUrl(string $subPath = ''): string
    {
        $adminPath = self::getAdminPath();
        $subPath = ltrim($subPath, '/');

        if ($subPath === '') {
            return '/' . $adminPath . '/';
        }

        return '/' . $adminPath . '/' . $subPath;
    }

    /**
     * 管理画面の物理パスを取得
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

    public static function isAdminPath(): bool
    {
        $adminPath = self::getAdminPath();
        $requestUri = $_SERVER['REQUEST_URI'] ?? '';

        return strpos($requestUri, '/' . $adminPath . '/') === 0
            || strpos($requestUri, '/' . $adminPath) === 0;
    }
}
