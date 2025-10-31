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

                // PDOオプションを設定
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

        // usersテーブル（管理者認証用）
        $db->exec("
            CREATE TABLE IF NOT EXISTS users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username TEXT NOT NULL UNIQUE,
                password_hash TEXT NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");

        // postsテーブル
        $db->exec("
            CREATE TABLE IF NOT EXISTS posts (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                title TEXT NOT NULL,
                tags TEXT,
                detail TEXT,
                image_path TEXT,
                thumb_path TEXT,
                is_sensitive INTEGER DEFAULT 0,
                is_visible INTEGER NOT NULL DEFAULT 1,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");

        // postsテーブルのインデックス
        $db->exec("CREATE INDEX IF NOT EXISTS idx_posts_created_at ON posts(created_at DESC)");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_posts_visible ON posts(is_visible, created_at DESC)");

        // tagsテーブル（タグマスタ）
        $db->exec("
            CREATE TABLE IF NOT EXISTS tags (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL UNIQUE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");

        // tagsテーブルのインデックス
        $db->exec("CREATE INDEX IF NOT EXISTS idx_tags_name ON tags(name)");

        // migrationsテーブル（マイグレーションバージョン管理）
        $db->exec("
            CREATE TABLE IF NOT EXISTS migrations (
                version INTEGER PRIMARY KEY,
                name TEXT NOT NULL,
                executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");

        // settingsテーブル
        $db->exec("
            CREATE TABLE IF NOT EXISTS settings (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                key TEXT NOT NULL UNIQUE,
                value TEXT NOT NULL,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
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

        // マイグレーションを実行
        self::runMigrations($db);
    }

    /**
     * マイグレーション実行
     */
    private static function runMigrations(PDO $db): void
    {
        // マイグレーション一覧（バージョン番号 => [名前, 関数]）
        $migrations = [
            1 => ['add_tag_columns', function($db) { self::migration_001_addTagColumns($db); }],
        ];

        // 実行済みマイグレーションを取得
        $stmt = $db->query("SELECT version FROM migrations");
        $executed = $stmt->fetchAll(PDO::FETCH_COLUMN);

        // 未実行のマイグレーションを実行
        foreach ($migrations as $version => $migration) {
            if (!in_array($version, $executed)) {
                [$name, $func] = $migration;

                try {
                    // マイグレーション実行
                    $func($db);

                    // 実行済みとして記録
                    $stmt = $db->prepare("INSERT INTO migrations (version, name) VALUES (?, ?)");
                    $stmt->execute([$version, $name]);

                    error_log("Migration {$version} ({$name}) executed successfully");
                } catch (\Exception $e) {
                    error_log("Migration {$version} ({$name}) failed: " . $e->getMessage());
                    throw $e;
                }
            }
        }
    }

    /**
     * マイグレーション 001: タグを分割カラム（tag1～tag10）に移行
     */
    private static function migration_001_addTagColumns(PDO $db): void
    {

        // tag1～tag10カラムを追加（INTEGER型でタグIDを保存）
        for ($i = 1; $i <= 10; $i++) {
            $db->exec("ALTER TABLE posts ADD COLUMN tag{$i} INTEGER");
        }

        // tag1～tag10にインデックスを追加（整数なので高速）
        for ($i = 1; $i <= 10; $i++) {
            $db->exec("CREATE INDEX IF NOT EXISTS idx_posts_tag{$i} ON posts(tag{$i})");
        }

        // 既存のtagsカラムからtag1～tag10にデータを移行
        $stmt = $db->query("SELECT id, tags FROM posts WHERE tags IS NOT NULL AND tags != ''");
        $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($posts as $post) {
            $tags = $post['tags'];
            if (empty($tags)) {
                continue;
            }

            // カンマで分割し、前後のスペース/タブを除去
            $tagArray = array_map('trim', explode(',', $tags));
            $tagArray = array_filter($tagArray, function($tag) {
                return !empty($tag);
            });

            // 最大10個まで
            $tagArray = array_slice($tagArray, 0, 10);

            // タグ名をタグIDに変換
            $tagIds = [];
            foreach ($tagArray as $tagName) {
                // タグを取得または作成
                $stmt = $db->prepare("INSERT OR IGNORE INTO tags (name) VALUES (?)");
                $stmt->execute([$tagName]);

                $stmt = $db->prepare("SELECT id FROM tags WHERE name = ?");
                $stmt->execute([$tagName]);
                $tag = $stmt->fetch();

                if ($tag) {
                    $tagIds[] = (int)$tag['id'];
                }
            }

            // tag1～tag10にタグIDを保存
            if (!empty($tagIds)) {
                $updates = [];
                $params = [];
                for ($i = 0; $i < count($tagIds); $i++) {
                    $colNum = $i + 1;
                    $updates[] = "tag{$colNum} = ?";
                    $params[] = $tagIds[$i];
                }

                $params[] = $post['id'];
                $sql = "UPDATE posts SET " . implode(', ', $updates) . " WHERE id = ?";
                $updateStmt = $db->prepare($sql);
                $updateStmt->execute($params);
            }
        }

        // 注意: tagsカラムは後方互換性のため残す（将来的に削除可能）
        // $db->exec("ALTER TABLE posts DROP COLUMN tags"); // SQLiteではDROPがサポートされていない
    }

    /**
     * 指定したマイグレーションが実行済みかチェック
     *
     * @param int $version マイグレーションバージョン
     * @return bool 実行済みの場合true
     */
    public static function isMigrationExecuted(int $version): bool
    {
        $db = self::getInstance();
        $stmt = $db->prepare("SELECT COUNT(*) FROM migrations WHERE version = ?");
        $stmt->execute([$version]);
        return $stmt->fetchColumn() > 0;
    }

    /**
     * 実行済みマイグレーション一覧を取得
     *
     * @return array マイグレーション情報の配列
     */
    public static function getExecutedMigrations(): array
    {
        $db = self::getInstance();
        $stmt = $db->query("SELECT version, name, executed_at FROM migrations ORDER BY version ASC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
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
