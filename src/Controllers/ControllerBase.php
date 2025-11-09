<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Utils\Logger;
use App\Services\Session;
use Exception;

/**
 * 共通コントローラ基底クラス
 *
 * 管理画面 / 公開 API 共通で使えるユーティリティを提供する。
 */
abstract class ControllerBase
{
    /**
     * JSON pretty-print flag for responses. Controlled via static setter.
     * When true, JSON responses include JSON_PRETTY_PRINT.
     */
    protected static bool $jsonPretty = false;

    /**
     * Enable or disable pretty JSON output for all controller responses.
     */
    public static function setJsonPretty(bool $enable): void
    {
        self::$jsonPretty = $enable;
    }
    /**
     * セッション開始のラッパー
     */
    protected function initSession(): void
    {
        try {
            Session::start();
        } catch (Exception $e) {
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
        }
    }

    /**
     * JSONレスポンスヘッダー設定
     */
    protected function setJsonHeader(): void
    {
        header('Content-Type: application/json; charset=utf-8');
    }

    /**
     * HTTPメソッド取得（_methodパラメータ対応）
     */
    protected function getHttpMethod(): string
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        if ($method === 'POST' && isset($_POST['_method'])) {
            $method = strtoupper($_POST['_method']);
        }
        return $method;
    }

    /**
     * 成功レスポンス送信
     */
    protected function sendSuccess(array $data = [], int $statusCode = 200): void
    {
        if ($statusCode !== 200) {
            http_response_code($statusCode);
        }

        $response = array_merge(['success' => true], $data);
        $opts = JSON_UNESCAPED_UNICODE;
        if (self::$jsonPretty) {
            $opts |= JSON_PRETTY_PRINT;
        }
        echo json_encode($response, $opts);
        exit;
    }

    /**
     * エラーレスポンス送信
     */
    protected function sendError(string $error, int $statusCode = 400, array $additionalData = []): void
    {
        http_response_code($statusCode);

        $response = array_merge(['success' => false, 'error' => $error], $additionalData);
        $opts = JSON_UNESCAPED_UNICODE;
        if (self::$jsonPretty) {
            $opts |= JSON_PRETTY_PRINT;
        }
        echo json_encode($response, $opts);
        exit;
    }

    /**
     * エラーハンドリング（例外キャッチ時の共通処理）
     */
    protected function handleError(Exception $e): void
    {
        http_response_code(500);
        $response = [
            'success' => false,
            'error' => 'Internal server error'
        ];
        $opts = JSON_UNESCAPED_UNICODE;
        if (self::$jsonPretty) {
            $opts |= JSON_PRETTY_PRINT;
        }
        echo json_encode($response, $opts);

        Logger::getInstance()->error(get_class($this) . ' Error: ' . $e->getMessage());
        exit;
    }

    /**
     * JSONデコード（PUT/PATCHリクエスト用）
     */
    protected function parseJsonInput(): array
    {
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        return $data ?? [];
    }

    /**
     * parse_strでデコード（PUT/PATCHリクエスト用）
     */
    protected function parseFormInput(): array
    {
        $input = file_get_contents('php://input');
        parse_str($input, $data);
        return $data;
    }

    /**
     * セキュリティイベントログ記録
     */
    protected function logSecurityEvent(string $message, array $context = []): void
    {
        if (function_exists('logSecurityEvent')) {
            $defaultContext = ['ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'];
            logSecurityEvent($message, array_merge($defaultContext, $context));
        }
    }
}
