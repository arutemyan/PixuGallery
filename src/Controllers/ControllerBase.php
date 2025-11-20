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
        header('X-Content-Type-Options: nosniff');
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

        $this->setJsonHeader();
        $response = array_merge(['success' => true], $data);
        echo $this->safeJsonEncode($response);
        exit;
    }

    /**
     * エラーレスポンス送信
     */
    protected function sendError(string $error, int $statusCode = 400, array $additionalData = []): void
    {
        http_response_code($statusCode);
        $this->setJsonHeader();
        $response = array_merge(['success' => false, 'error' => $error], $additionalData);
        echo $this->safeJsonEncode($response);
        exit;
    }

    /**
     * エラーハンドリング（例外キャッチ時の共通処理）
     */
    protected function handleError(Exception $e): void
    {
        http_response_code(500);
        $this->setJsonHeader();
        $response = [
            'success' => false,
            'error' => 'Internal server error'
        ];
        echo $this->safeJsonEncode($response);

        Logger::getInstance()->error(get_class($this) . ' Error: ' . $e->getMessage());
        exit;
    }

    /**
     * JSONデコード（PUT/PATCHリクエスト用）
     */
    protected function parseJsonInput(): array
    {
        $input = file_get_contents('php://input');
        if ($input === false || $input === '') {
            return [];
        }
        try {
            $data = json_decode($input, true, 512, JSON_THROW_ON_ERROR);
            return $data ?? [];
        } catch (\JsonException $e) {
            $this->sendError('Invalid JSON input', 400);
        }
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
     * 安全に JSON をエンコードして返す。エンコードに失敗した場合はログ記録して 500 を返す。
     *
     * @param mixed $data
     * @return string
     */
    protected function safeJsonEncode(mixed $data): string
    {
        try {
            $opts = JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR;
            if (self::$jsonPretty) {
                $opts |= JSON_PRETTY_PRINT;
            }
            return json_encode($data, $opts);
        } catch (\JsonException $e) {
            Logger::getInstance()->error(get_class($this) . ' JSON encode error: ' . $e->getMessage());
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
            header('X-Content-Type-Options: nosniff');
            echo '{"success":false,"error":"Internal server error"}';
            exit;
        }
    }

    /**
     * リクエストが JSON であるかどうかを推定する。主に Content-Type を確認する。
     */
    protected function isJsonRequest(): bool
    {
        $contentType = $_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? '';
        if ($contentType !== '' && stripos($contentType, 'application/json') !== false) {
            return true;
        }
        $length = (int)($_SERVER['CONTENT_LENGTH'] ?? 0);
        return $length > 0;
    }

    /**
     * JSON を期待するエンドポイントで呼び出す。期待に反する場合は 415 を返す。
     */
    protected function requireJson(bool $allowEmpty = false): void
    {
        if (!$this->isJsonRequest()) {
            $this->sendError('Expected application/json', 415);
        }
        if (!$allowEmpty) {
            $len = (int)($_SERVER['CONTENT_LENGTH'] ?? 0);
            if ($len === 0) {
                $this->sendError('Empty JSON body', 400);
            }
        }
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
