<?php

declare(strict_types=1);

namespace App\Database;

use PDO;
use PDOException;

/**
 * カウンターデータベース接続クラス
 *
 * 閲覧数などの頻繁に更新されるカウンターを管理
 * メインDBとは分離して書き込みロックの競合を回避
 */
class CountersConnection
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
     * データベース接続を取得
     *
     * @return PDO
     * @throws PDOException データベース接続エラー
     */
    public static function getInstance(): PDO
    {
        if (self::$instance === null) {
            try {
                self::loadConfig();
                $dbPath = self::$config['database']['counters']['path'];

                // データベースディレクトリが存在しない場合は作成
                $dbDir = dirname($dbPath);
                if (!is_dir($dbDir)) {
                    $permission = self::$config['directory_permission'] ?? 0755;
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
                throw new PDOException('カウンターデータベース接続エラー: ' . $e->getMessage());
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

        // view_countsテーブル（投稿の閲覧数）
        $db->exec("
            CREATE TABLE IF NOT EXISTS view_counts (
                post_id INTEGER PRIMARY KEY,
                count INTEGER DEFAULT 0,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");

        // インデックス作成
        $db->exec("CREATE INDEX IF NOT EXISTS idx_view_counts_updated ON view_counts(updated_at DESC)");
    }

    /**
     * 接続をクローズ（主にテスト用）
     */
    public static function close(): void
    {
        self::$instance = null;
    }
}
