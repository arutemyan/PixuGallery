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
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $token = bin2hex(random_bytes(self::TOKEN_LENGTH));
        $_SESSION[self::SESSION_KEY] = $token;

        return $token;
    }

    /**
     * CSRFトークンを取得（既存のトークンまたは新規生成）
     *
     * @return string トークン文字列
     */
    public static function getToken(): string
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (!isset($_SESSION[self::SESSION_KEY])) {
            return self::generateToken();
        }

        return $_SESSION[self::SESSION_KEY];
    }

    /**
     * CSRFトークンを検証
     *
     * @param string|null $token 検証するトークン
     * @return bool 有効な場合true
     */
    public static function validateToken(?string $token): bool
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if ($token === null) {
            return false;
        }

        // First, check the legacy/global session key
        if (isset($_SESSION[self::SESSION_KEY]) && hash_equals((string)$_SESSION[self::SESSION_KEY], (string)$token)) {
            return true;
        }

        // Backwards-compat: check the namespaced app session key used by Session::getCsrfToken()
        if (isset($_SESSION['_app_session']) && is_array($_SESSION['_app_session']) && isset($_SESSION['_app_session'][self::SESSION_KEY]) && hash_equals((string)$_SESSION['_app_session'][self::SESSION_KEY], (string)$token)) {
            return true;
        }

        return false;
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
        $headers = getallheaders();
        $token = $headers[$headerName] ?? null;
        return self::validateToken($token);
    }

    /**
     * セッションをクリア
     */
    public static function clearSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        unset($_SESSION[self::SESSION_KEY]);
    }
}
