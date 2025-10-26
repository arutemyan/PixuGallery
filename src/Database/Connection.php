<?php

declare(strict_types=1);

namespace App\Database;

use PDO;
use PDOException;

require_once __DIR__ . '/../Security/SecurityUtil.php';

/**
 * SQLite データベース接続クラス
 *
 * シングルトンパターンでPDO接続を管理
 */
class Connection
{
    private static ?PDO $instance = null;
    private static ?string $dbPath = null;
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
            }
        }
    }

    /**
     * データベースパスを取得
     */
    private static function getDatabasePath(): string
    {
        if (self::$dbPath !== null) {
            return self::$dbPath;
        }

        self::loadConfig();
        return self::$config['database']['gallery']['path'];
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
                $dbPath = self::getDatabasePath();

                // データベースディレクトリを作成して保護
                $dbDir = dirname($dbPath);
                $permission = self::$config['directory_permission'] ?? 0755;
                ensureSecureDirectory($dbDir, $permission);

                self::$instance = new PDO(
                    'sqlite:' . $dbPath,
                    null,
                    null,
                    self::$config['database']['pdo_options']
                );

                // データベーススキーマを初期化
                self::initializeSchema();
            } catch (PDOException $e) {
                throw new PDOException('データベース接続エラー: ' . $e->getMessage());
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

        // postsテーブル
        $db->exec("
            CREATE TABLE IF NOT EXISTS posts (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                title TEXT NOT NULL,
                tags TEXT,
                detail TEXT,
                image_path TEXT,
		thumb_path TEXT,
                is_sensitive TINYINT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");

        // usersテーブル（管理者認証用）
        $db->exec("
            CREATE TABLE IF NOT EXISTS users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username TEXT NOT NULL UNIQUE,
                password_hash TEXT NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");

        // themesテーブル（テーマカスタマイズ用）
        $db->exec("
            CREATE TABLE IF NOT EXISTS themes (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                header_html TEXT,
                footer_html TEXT,
                site_title TEXT DEFAULT 'イラストポートフォリオ',
                site_subtitle TEXT DEFAULT 'Illustration Portfolio',
                site_description TEXT DEFAULT 'イラストレーターのポートフォリオサイト',
                primary_color TEXT DEFAULT '#8B5AFA',
                secondary_color TEXT DEFAULT '#667eea',
                accent_color TEXT DEFAULT '#FFD700',
                background_color TEXT DEFAULT '#1a1a1a',
                text_color TEXT DEFAULT '#ffffff',
                heading_color TEXT DEFAULT '#ffffff',
                footer_bg_color TEXT DEFAULT '#2a2a2a',
                footer_text_color TEXT DEFAULT '#cccccc',
                card_border_color TEXT DEFAULT '#333333',
                card_bg_color TEXT DEFAULT '#252525',
                card_shadow_opacity TEXT DEFAULT '0.3',
                link_color TEXT DEFAULT '#8B5AFA',
                link_hover_color TEXT DEFAULT '#a177ff',
                tag_bg_color TEXT DEFAULT '#8B5AFA',
                tag_text_color TEXT DEFAULT '#ffffff',
                filter_active_bg_color TEXT DEFAULT '#8B5AFA',
                filter_active_text_color TEXT DEFAULT '#ffffff',
                header_image TEXT,
                logo_image TEXT,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");

        // 管理者ユーザーの自動作成は行わない
        // 初回セットアップはpublic/setup.phpで行う

        // デフォルトテーマを作成（存在しない場合）
        $stmt = $db->query("SELECT COUNT(*) as count FROM themes");
        $result = $stmt->fetch();

        if ($result['count'] == 0) {
            $db->exec("INSERT INTO themes (header_html, footer_html) VALUES ('', '')");
        }
    }

    /**
     * テスト用にデータベースパスを設定
     *
     * @param string $path データベースファイルのパス
     */
    public static function setDatabasePath(string $path): void
    {
        self::$dbPath = $path;
        self::$instance = null; // インスタンスをリセット
    }

    /**
     * 接続をクローズ（主にテスト用）
     */
    public static function close(): void
    {
        self::$instance = null;
    }
}
