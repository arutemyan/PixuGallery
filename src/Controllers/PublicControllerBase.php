<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Utils\Logger;
use App\Services\Session;
use Exception;

/**
 * 公開 API 向けコントローラ基底（軽量）
 *
 * ControllerBase のユーティリティをラップし、public API スクリプトから
 * 呼び出しやすい静的ヘルパを提供する（段階的移行のための薄い層）。
 */
abstract class PublicControllerBase extends ControllerBase
{
    // By default, public controllers do not start sessions.
    // Keep this as a no-op so instance-based execute() won't create sessions
    // unless a specific controller overrides this behavior.
    protected function initSession(): void
    {
        return; // no session by default for public controllers
    }

    protected function checkAuthentication(): void
    {
        return;
    }
    
    /**
     * 固有処理（継承先で実装）
     * 
     * @param string $method HTTPメソッド（GET, POST, PUT, DELETE, PATCH等）
     */
    abstract protected function onProcess(string $method): void;

    public function isJsonPretty(): bool { return false; }

    /**
     * エントリーポイント
     * 共通処理を実行してからonProcessを呼び出す
     */
    public function execute(): void
    {
        self::setJsonPretty($this->isJsonPretty());
        try {
            // CORS handling and session/json configuration based on instance properties
            if (property_exists($this, 'allowCors') && $this->allowCors) {
                $origin = $this->corsOrigin ?? '*';
                $methods = $this->allowMethods ?? 'GET, POST, OPTIONS';
                $headers = $this->allowHeaders ?? 'Content-Type, X-CSRF-Token';
                header('Access-Control-Allow-Origin: ' . $origin);
                header('Access-Control-Allow-Methods: ' . $methods);
                header('Access-Control-Allow-Headers: ' . $headers);
                if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
                    http_response_code(204);
                    exit;
                }
            }

            if (property_exists($this, 'startSession') && $this->startSession) {
                parent::initSession();
            }

            // JSONレスポンスヘッダー設定
            $this->setJsonHeader();

            // HTTPメソッド取得
            $method = $this->getHttpMethod();

            // 固有処理を実行
            $this->onProcess($method);

        } catch (Exception $e) {
            $this->handleError($e);
        }
    }
}
