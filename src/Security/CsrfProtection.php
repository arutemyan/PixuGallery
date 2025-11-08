<?php

declare(strict_types=1);

namespace App\Security;

/**
 * CSRF トークン保護クラス
 *
 * CSRF攻撃からアプリケーションを保護
 */
class CsrfProtection
{
    private const TOKEN_LENGTH = 32;
    private const SESSION_KEY = 'csrf_token';

    /**
     * CSRFトークンを生成
     *
     * @return string トークン文字列
     */
    public static function generateToken(): string
    {
        // Delegate generation to Session service
        if (!class_exists('\App\\Services\\Session')) {
            throw new \RuntimeException('Session service is required for CSRF token generation');
        }
        \App\Services\Session::start();
        $sess = \App\Services\Session::getInstance();
        return $sess->getCsrfToken();
    }

    /**
     * CSRFトークンを取得（既存のトークンまたは新規生成）
     *
     * @return string トークン文字列
     */
    public static function getToken(): string
    {
        if (!class_exists('\App\\Services\\Session')) {
            throw new \RuntimeException('Session service is required for CSRF token retrieval');
        }
        \App\Services\Session::start();
        $sess = \App\Services\Session::getInstance();
        return $sess->getCsrfToken();
    }

    /**
     * CSRFトークンを検証
     *
     * @param string|null $token 検証するトークン
     * @return bool 有効な場合true
     */
    public static function validateToken(?string $token): bool
    {
        if ($token === null) {
            return false;
        }
        if (!class_exists('\App\\Services\\Session')) {
            throw new \RuntimeException('Session service is required for CSRF token validation');
        }
        \App\Services\Session::start();
        $sess = \App\Services\Session::getInstance();
        return $sess->validateCsrf($token);
    }

    /**
     * POSTリクエストのCSRFトークンを検証
     *
     * @param string $fieldName フォームフィールド名（デフォルト: 'csrf_token'）
     * @return bool 有効な場合true
     */
    public static function validatePost(string $fieldName = 'csrf_token'): bool
    {
        $token = $_POST[$fieldName] ?? null;
        return self::validateToken($token);
    }

    /**
     * ヘッダーのCSRFトークンを検証
     *
     * @param string $headerName ヘッダー名（デフォルト: 'X-CSRF-Token'）
     * @return bool 有効な場合true
     */
    public static function validateHeader(string $headerName = 'X-CSRF-Token'): bool
    {
        $headers = function_exists('getallheaders') ? getallheaders() : [];
        $token = $headers[$headerName] ?? null;
        return self::validateToken($token);
    }

    /**
     * セッションをクリア
     */
    public static function clearSession(): void
    {
        if (!class_exists('\App\\Services\\Session')) {
            throw new \RuntimeException('Session service is required to clear CSRF token');
        }
        \App\Services\Session::start();
        $sess = \App\Services\Session::getInstance();
        $sess->delete(self::SESSION_KEY);
    }
}
