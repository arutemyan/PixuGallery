<?php

declare(strict_types=1);

namespace App\Utils;



/**
 * Loggerクラス
 *
 * アプリケーションのログを管理するSingletonクラス
 */

class Logger
{
    private static ?Logger $instance = null;
    private array $config;
    private array $logLevels = [
        'debug' => 0,
        'info' => 1,
        'warning' => 2,
        'error' => 3
    ];

    /**
     * コンストラクタ（プライベート）
     */
    private function __construct()
    {
        $this->config = \App\Config\ConfigManager::getInstance()->get('app_logging', []);
        // 必須チェック: log_file は必須設定とする（config.default.php にデフォルトがあることを前提）
        if (empty($this->config['log_file'])) {
            throw new \RuntimeException('app_logging.log_file is not configured');
        }

        // ログディレクトリが存在しない場合は作成（失敗したら例外）
        $logDir = dirname($this->config['log_file']);
        if (!is_dir($logDir)) {
            if (!@mkdir($logDir, 0755, true)) {
                throw new \RuntimeException(sprintf('Failed to create log directory: %s', $logDir));
            }
        }
    }

    /**
     * インスタンスを取得（Singleton）
     */
    public static function getInstance(): Logger
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * デバッグログ
     */
    public function debug(string $message): void
    {
        $this->log('debug', $message);
    }

    /**
     * 情報ログ
     */
    public function info(string $message): void
    {
        $this->log('info', $message);
    }

    /**
     * 警告ログ
     */
    public function warning(string $message): void
    {
        $this->log('warning', $message);
    }

    /**
     * エラーログ
     */
    public function error(string $message): void
    {
        $this->log('error', $message);
    }

    /**
     * ログ出力
     */
    private function log(string $level, string $message): void
    {
        if (!$this->config['enabled'] || !$this->shouldLog($level)) {
            return;
        }

        // 呼び出し元のファイルと行を取得
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
        $caller = $backtrace[1] ?? [];
        $file = $caller['file'] ?? 'unknown';
        $line = $caller['line'] ?? 0;

        // 相対ファイルパスを取得
        $relativeFile = $this->getRelativeFile($file);

        // タイムスタンプ
        $timestamp = date('Y-m-d H:i:s');

        // フォーマット適用
        $logLine = str_replace(
            ['%timestamp', '%level', '%file', '%line', '%message'],
            [$timestamp, strtoupper($level), $relativeFile, $line, $message],
            $this->config['format']
        ) . "\n";

        // ファイルに書き込み
        $res = @file_put_contents($this->config['log_file'], $logLine, FILE_APPEND | LOCK_EX);
        if ($res === false) {
            throw new \RuntimeException(sprintf('Failed to write to log file: %s', $this->config['log_file']));
        }
    }

    /**
     * 指定レベルをログ出力すべきか判定
     */
    private function shouldLog(string $level): bool
    {
        $currentLevel = $this->config['level'] ?? 'error';
        return ($this->logLevels[$level] ?? 0) >= ($this->logLevels[$currentLevel] ?? 3);
    }

    /**
     * 相対ファイルパスを取得
     */
    private function getRelativeFile(string $file): string
    {
        $root = realpath(__DIR__ . '/../../');
        if ($root === false) {
            return $file;
        }
        $relative = str_replace($root . DIRECTORY_SEPARATOR, '', $file);
        return str_replace(DIRECTORY_SEPARATOR, '/', $relative);
    }
}