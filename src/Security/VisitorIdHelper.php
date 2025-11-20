<?php

declare(strict_types=1);

namespace App\Security;

/**
 * Visitor ID cookie の管理ヘルパー
 * - Cookie の検証・生成を一箇所にまとめる
 * - 戻り値は常に visitorId（生のIDのまま）
 */
class VisitorIdHelper
{
    /**
     * visitor id を検証して返す。なければ新規発行して cookie をセットする。
     *
     * @param string $secret HMAC シークレット（空なら簡易ハッシュで発行）
     * @return string visitorId
     */
    public static function getOrCreate(string $secret = ''): string
    {
        // If caller didn't provide a secret, resolve using env/config fallbacks:
        // 1) APP_PUBLIC_ID_SECRET env
        // 2) config.security.public_id_secret
        // 3) config.security.id_secret (fallback)
        if (empty($secret)) {
            $cfg = \App\Config\ConfigManager::getInstance()->getConfig();
            $env = getenv('APP_PUBLIC_ID_SECRET');
            if (!empty($env)) {
                $secret = $env;
            } elseif (!empty($cfg['security']['public_id_secret'] ?? '')) {
                $secret = $cfg['security']['public_id_secret'];
            } elseif (!empty($cfg['security']['id_secret'] ?? '')) {
                $secret = $cfg['security']['id_secret'];
            } else {
                $secret = '';
            }
        }

        $visitorId = null;

        if (!empty($_COOKIE['visitor_id']) && is_string($_COOKIE['visitor_id']) && strpos($_COOKIE['visitor_id'], ':') !== false && !empty($secret)) {
            list($vid, $sig) = explode(':', $_COOKIE['visitor_id'], 2);
            $expected = hash_hmac('sha256', $vid, $secret);
            if (hash_equals($expected, $sig)) {
                $visitorId = $vid;
            }
        } elseif (!empty($_COOKIE['visitor_id']) && is_string($_COOKIE['visitor_id']) && strpos($_COOKIE['visitor_id'], ':') !== false && empty($secret)) {
            // secret がない運用（テストや簡易環境）のためのフォールバック
            list($vid, $sig) = explode(':', $_COOKIE['visitor_id'], 2);
            if (hash('sha256', $vid) === $sig) {
                $visitorId = $vid;
            }
        }

        if ($visitorId === null) {
            try {
                $newId = bin2hex(random_bytes(16));
            } catch (\Exception $e) {
                $newId = substr(str_replace(['+', '/', '='], '', base64_encode(random_bytes(12))), 0, 32);
            }
            if (!empty($secret)) {
                $sig = hash_hmac('sha256', $newId, $secret);
            } else {
                $sig = hash('sha256', $newId);
            }
            $cookieValue = $newId . ':' . $sig;

            setcookie('visitor_id', $cookieValue, [
                'expires' => time() + 60 * 60 * 24 * 365,
                'path' => '/',
                'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
                'httponly' => true,
                'samesite' => 'Lax',
            ]);

            $visitorId = $newId;
        }

        return $visitorId;
    }
}
