<?php

declare(strict_types=1);

namespace App\Database;

use PDO;
use PDOException;

/**
 * アクセスログデータベース接続クラス
 *
 * オプション機能として詳細なアクセスログを記録
 * 設定でON/OFFを切り替え可能
 */
class AccessLogsConnection
{
    private static ?PDO $instance = null;
    private static ?array $config = null;

    /**
     * コンストラクタをプライベートに（シングルトン）
     */
    private function __construct()
    {
    }

    /**
     * 設定ファイルを読み込み
     */
    private static function loadConfig(): void
    {
        if (self::$config === null) {
            $configPath = __DIR__ . '/../../config/config.php';
            if (file_exists($configPath)) {
                self::$config = require $configPath;
            } else {
                throw new PDOException('Database configuration file not found');
            }
        }
    }

    /**
     * アクセスログ機能が有効かチェック
     *
     * @return bool
     */
    public static function isEnabled(): bool
    {
        self::loadConfig();
        return self::$config['database']['access_logs']['enabled'] ?? false;
    }

    /**
     * データベース接続を取得
     *
     * @return PDO|null 無効の場合はnull
     * @throws PDOException データベース接続エラー
     */
    public static function getInstance(): ?PDO
    {
        // アクセスログが無効の場合はnullを返す
        if (!self::isEnabled()) {
            return null;
        }

        if (self::$instance === null) {
            try {
                self::loadConfig();
                $dbPath = self::$config['database']['access_logs']['path'];

                // データベースディレクトリが存在しない場合は作成
                $dbDir = dirname($dbPath);
                if (!is_dir($dbDir)) {
                    $permission = self::$config['database']['directory_permission'] ?? 0755;
                    mkdir($dbDir, $permission, true);
                }

                self::$instance = new PDO(
                    'sqlite:' . $dbPath,
                    null,
                    null,
                    self::$config['database']['pdo_options']
                );

                // データベーススキーマを初期化
                self::initializeSchema();
            } catch (PDOException $e) {
                throw new PDOException('アクセスログデータベース接続エラー: ' . $e->getMessage());
            }
        }

        return self::$instance;
    }

    /**
     * データベーススキーマを初期化
     */
    private static function initializeSchema(): void
    {
        $db = self::$instance;

        // access_logsテーブル（詳細なアクセスログ）
        $db->exec("
            CREATE TABLE IF NOT EXISTS access_logs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                post_id INTEGER,
                ip_address TEXT,
                user_agent TEXT,
                referer TEXT,
                accessed_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");

        // インデックス作成
        $db->exec("CREATE INDEX IF NOT EXISTS idx_access_logs_post_id ON access_logs(post_id)");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_access_logs_accessed ON access_logs(accessed_at DESC)");
    }

    /**
     * 接続をクローズ（主にテスト用）
     */
    public static function close(): void
    {
        self::$instance = null;
    }
}
