<?php

declare(strict_types=1);

namespace App\Database;

use PDO;
use PDOException;

require_once __DIR__ . '/../Security/SecurityUtil.php';

/**
 * カウンターデータベース接続クラス
 *
 * 閲覧数などの頻繁に更新されるカウンターを管理
 *
 * - SQLite: メインDBとは分離して書き込みロックの競合を回避（counters.db）
 * - MySQL/PostgreSQL: 1DB構成のためConnection::getInstance()を返す
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
        // MySQL/PostgreSQLの場合は、Connectionと同じインスタンスを返す（1DB構成）
        self::loadConfig();
        $driver = self::$config['database']['driver'] ?? 'sqlite';

        if ($driver !== 'sqlite') {
            return Connection::getInstance();
        }

        // SQLiteの場合は専用のcounters.dbを使用
        if (self::$instance === null) {
            try {
                self::loadConfig();
                $dbPath = self::$config['database']['sqlite']['counters']['path'];

                // データベースディレクトリを作成して保護
                $dbDir = dirname($dbPath);
                $permission = self::$config['database']['directory_permission'] ?? 0755;
                ensureSecureDirectory($dbDir, $permission);

                $pdoOptions = [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ];

                self::$instance = new PDO(
                    'sqlite:' . $dbPath,
                    null,
                    null,
                    $pdoOptions
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
     * データベーススキーマを初期化（SQLiteのみ）
     */
    private static function initializeSchema(): void
    {
        $db = self::$instance;

        // view_countsテーブルの存在確認
        $stmt = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='view_counts'");
        $tableExists = $stmt->fetch() !== false;

        if (!$tableExists) {
            // 新規作成：最初からpost_typeを含める
            $db->exec("
                CREATE TABLE view_counts (
                    post_id INTEGER NOT NULL,
                    post_type INTEGER DEFAULT 0 NOT NULL,
                    count INTEGER DEFAULT 0,
                    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (post_id, post_type)
                )
            ");
        } else {
            // 既存テーブル：post_typeカラムの追加が必要かチェック
            $stmt = $db->query("PRAGMA table_info(view_counts)");
            $columns = $stmt->fetchAll();
            $hasPostType = false;

            foreach ($columns as $column) {
                if ($column['name'] === 'post_type') {
                    $hasPostType = true;
                    break;
                }
            }

            if (!$hasPostType) {
                // post_typeカラムを追加してテーブルを再作成
                $db->exec("BEGIN TRANSACTION");

                try {
                    // 既存データを一時テーブルにコピー
                    $db->exec("CREATE TABLE view_counts_temp AS SELECT * FROM view_counts");

                    // 古いテーブルを削除
                    $db->exec("DROP TABLE view_counts");

                    // 新しいスキーマでテーブルを作成
                    $db->exec("
                        CREATE TABLE view_counts (
                            post_id INTEGER NOT NULL,
                            post_type INTEGER DEFAULT 0 NOT NULL,
                            count INTEGER DEFAULT 0,
                            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                            PRIMARY KEY (post_id, post_type)
                        )
                    ");

                    // データを戻す（既存データは全てpost_type=0として扱う）
                    $db->exec("
                        INSERT INTO view_counts (post_id, post_type, count, updated_at)
                        SELECT post_id, 0, count, updated_at FROM view_counts_temp
                    ");

                    // 一時テーブルを削除
                    $db->exec("DROP TABLE view_counts_temp");

                    $db->exec("COMMIT");

                    error_log("CountersConnection: Added post_type column to view_counts table");
                } catch (\Exception $e) {
                    $db->exec("ROLLBACK");
                    error_log("CountersConnection: Failed to add post_type column: " . $e->getMessage());
                    throw $e;
                }
            }
        }

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
