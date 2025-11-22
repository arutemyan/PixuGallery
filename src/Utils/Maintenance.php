<?php

declare(strict_types=1);

namespace App\Utils;

use App\Config\ConfigManager;

class Maintenance
{
    public static function isEnabled(): bool
    {
        try {
            $cfg = ConfigManager::getInstance()->getConfig();
            $val = $cfg['app']['maintenance_mode'] ?? 0;
            return (int)$val === 1;
        } catch (\Throwable $e) {
            return false;
        }
    }

    private static function isAdminRequest(): bool
    {
        $cfg = ConfigManager::getInstance()->getConfig();
        $adminPath = $cfg['admin']['path'] ?? 'admin';
        $requestUri = $_SERVER['REQUEST_URI'] ?? '';
        return strpos($requestUri, '/' . $adminPath . '/') === 0 || strpos($requestUri, '/' . $adminPath) === 0;
    }

    /**
     * Enforce maintenance for normal page requests (HTML). Exits with 503 and renders a simple page.
     */
    public static function enforceForPages(): void
    {
        if (!self::isEnabled()) return;

        // Allow admin path to work so operators can access the admin UI
        if (self::isAdminRequest()) return;

        // Send 503 and simple maintenance page
        if (!headers_sent()) {
            http_response_code(503);
            header('Retry-After: 3600');
            header('Content-Type: text/html; charset=utf-8');
        }

        $tpl = __DIR__ . '/../../templates/maintenance.php';
        if (is_file($tpl)) {
            include $tpl;
            exit;
        }

        echo '<!doctype html><html><head><meta charset="utf-8"><title>メンテナンス中</title></head><body><h1>メンテナンス中</h1><p>ただいまメンテナンス作業中です。しばらくお待ちください。</p></body></html>';
        exit;
    }

    /**
     * Enforce maintenance for API endpoints. Returns JSON 503 and exits.
     */
    public static function enforceForApi(): void
    {
        if (!self::isEnabled()) return;

        if (self::isAdminRequest()) return;

        if (!headers_sent()) {
            http_response_code(503);
            header('Content-Type: application/json; charset=utf-8');
            header('Retry-After: 3600');
        }

        echo json_encode(['success' => false, 'error' => 'Service unavailable (maintenance)'], JSON_UNESCAPED_UNICODE);
        exit;
    }
}
