<?php

declare(strict_types=1);

namespace App\Error;

use App\Utils\Logger;
use Throwable;

/**
 * グローバルエラーハンドラー
 *
 * HTTP 500エラーが発生した場合にCloudflare風のエラーページを表示します。
 */
class ErrorHandler
{
    private static bool $registered = false;
    private static bool $production = false;
    private static ?Logger $logger = null;

    /**
     * エラーハンドラーを登録
     *
     * @param bool $production 本番モードかどうか
     */
    public static function register(bool $production = false): void
    {
        if (self::$registered) {
            return;
        }

        self::$production = $production;
        self::$registered = true;

        // ロガーのインスタンスを取得
        try {
            self::$logger = Logger::getInstance();
        } catch (Throwable $e) {
            // ロガーの初期化に失敗してもエラーハンドラーは動作させる
            self::$logger = null;
        }

        // エラーハンドラーを設定
        set_error_handler([self::class, 'handleError']);

        // 例外ハンドラーを設定
        set_exception_handler([self::class, 'handleException']);

        // シャットダウン時のFatal Errorをキャッチ
        register_shutdown_function([self::class, 'handleShutdown']);
    }

    /**
     * PHPエラーハンドラー
     *
     * @param int $errno エラー番号
     * @param string $errstr エラーメッセージ
     * @param string $errfile エラーファイル
     * @param int $errline エラー行
     * @return bool
     */
    public static function handleError(int $errno, string $errstr, string $errfile, int $errline): bool
    {
        // error_reporting で指定されたエラーレベル以外は無視
        if (!(error_reporting() & $errno)) {
            return false;
        }

        // エラーレベルに応じた処理
        $errorType = self::getErrorType($errno);
        $message = "[$errorType] $errstr in $errfile on line $errline";

        // ログに記録
        self::log('error', $message);

        // Fatal Errorの場合は500エラーを表示
        if (in_array($errno, [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR])) {
            self::display500Error($message);
            return true;
        }

        // 通常のエラーハンドリングを継続
        return false;
    }

    /**
     * 未キャッチの例外ハンドラー
     *
     * @param Throwable $exception
     */
    public static function handleException(Throwable $exception): void
    {
        $message = sprintf(
            "Uncaught %s: %s in %s:%d\nStack trace:\n%s",
            get_class($exception),
            $exception->getMessage(),
            $exception->getFile(),
            $exception->getLine(),
            $exception->getTraceAsString()
        );

        // ログに記録
        self::log('error', $message);

        // 500エラーページを表示
        self::display500Error($message);
    }

    /**
     * シャットダウンハンドラー（Fatal Error検出用）
     */
    public static function handleShutdown(): void
    {
        $error = error_get_last();

        if ($error !== null && in_array($error['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE])) {
            $message = sprintf(
                "[FATAL] %s in %s on line %d",
                $error['message'],
                $error['file'],
                $error['line']
            );

            // ログに記録
            self::log('error', $message);

            // 500エラーページを表示
            self::display500Error($message);
        }
    }

    /**
     * 500エラーページを表示
     *
     * @param string $errorMessage エラーメッセージ（開発環境でのみ表示）
     */
    private static function display500Error(string $errorMessage = ''): void
    {
        // 既に出力が開始されている場合はバッファをクリア
        if (ob_get_length()) {
            ob_clean();
        }

        // HTTPステータスコードを設定
        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: text/html; charset=UTF-8');
        }

        // 本番環境の場合はカスタムエラーページを表示
        if (self::$production) {
            $errorPagePath = __DIR__ . '/../../public/error/500.html';
            if (file_exists($errorPagePath)) {
                readfile($errorPagePath);
                exit;
            }
        }

        // 開発環境または500.htmlが見つからない場合は詳細なエラーを表示
        self::displayDetailedError($errorMessage);
        exit;
    }

    /**
     * 詳細なエラー情報を表示（開発環境用）
     *
     * @param string $errorMessage エラーメッセージ
     */
    private static function displayDetailedError(string $errorMessage): void
    {
        $errorMessage = htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8');
        $time = date('Y-m-d H:i:s');

        echo <<<HTML
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>500 Internal Server Error - Development Mode</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: #1a1a1a;
            color: #e0e0e0;
            padding: 40px;
            margin: 0;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        h1 {
            color: #ff6b6b;
            font-size: 32px;
            margin-bottom: 20px;
        }
        .error-box {
            background: #2d2d2d;
            border-left: 4px solid #ff6b6b;
            padding: 20px;
            margin: 20px 0;
            border-radius: 4px;
            font-family: 'Monaco', 'Menlo', 'Courier New', monospace;
            font-size: 14px;
            white-space: pre-wrap;
            word-wrap: break-word;
            overflow-x: auto;
        }
        .info {
            color: #888;
            font-size: 12px;
            margin-top: 20px;
        }
        .warning {
            background: #fff3cd;
            color: #856404;
            padding: 15px;
            border-radius: 4px;
            margin: 20px 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>500 Internal Server Error</h1>
        <div class="warning">
            <strong>Development Mode:</strong> This detailed error page is only shown in development environment.
            In production, users will see a user-friendly Cloudflare-style error page.
        </div>
        <div class="error-box">{$errorMessage}</div>
        <div class="info">
            Error occurred at: {$time}<br>
            To switch to production error page, set environment to 'production' in config.
        </div>
    </div>
</body>
</html>
HTML;
    }

    /**
     * エラーログに記録
     *
     * @param string $level ログレベル
     * @param string $message メッセージ
     */
    private static function log(string $level, string $message): void
    {
        if (self::$logger !== null) {
            try {
                // Loggerのpublicメソッドを使用
                switch ($level) {
                    case 'debug':
                        self::$logger->debug($message);
                        break;
                    case 'info':
                        self::$logger->info($message);
                        break;
                    case 'warning':
                        self::$logger->warning($message);
                        break;
                    case 'error':
                    default:
                        self::$logger->error($message);
                        break;
                }
            } catch (Throwable $e) {
                // ログ記録に失敗しても処理は継続
                error_log("Failed to log error: " . $e->getMessage());
            }
        } else {
            // Loggerが使えない場合はerror_logにフォールバック
            error_log("[$level] $message");
        }
    }

    /**
     * エラー番号からエラータイプ名を取得
     *
     * @param int $errno
     * @return string
     */
    private static function getErrorType(int $errno): string
    {
        $errorTypes = [
            E_ERROR => 'ERROR',
            E_WARNING => 'WARNING',
            E_PARSE => 'PARSE',
            E_NOTICE => 'NOTICE',
            E_CORE_ERROR => 'CORE_ERROR',
            E_CORE_WARNING => 'CORE_WARNING',
            E_COMPILE_ERROR => 'COMPILE_ERROR',
            E_COMPILE_WARNING => 'COMPILE_WARNING',
            E_USER_ERROR => 'USER_ERROR',
            E_USER_WARNING => 'USER_WARNING',
            E_USER_NOTICE => 'USER_NOTICE',
            E_STRICT => 'STRICT',
            E_RECOVERABLE_ERROR => 'RECOVERABLE_ERROR',
            E_DEPRECATED => 'DEPRECATED',
            E_USER_DEPRECATED => 'USER_DEPRECATED',
        ];

        return $errorTypes[$errno] ?? 'UNKNOWN';
    }
}
