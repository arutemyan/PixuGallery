<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use App\Database\Connection;

echo "=== グループ投稿機能マイグレーション (v2) ===\n";
echo "新設計: group_posts と group_post_images テーブルを作成\n\n";

try {
    $db = Connection::getInstance();

    echo "1. group_posts テーブルを作成...\n";

    // グループ投稿のメタデータテーブル
    $db->exec("
        CREATE TABLE IF NOT EXISTS group_posts (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            title TEXT NOT NULL,
            detail TEXT,
            is_sensitive INTEGER DEFAULT 0,
            is_visible INTEGER DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            tag1 INTEGER,
            tag2 INTEGER,
            tag3 INTEGER,
            tag4 INTEGER,
            tag5 INTEGER,
            tag6 INTEGER,
            tag7 INTEGER,
            tag8 INTEGER,
            tag9 INTEGER,
            tag10 INTEGER,
            FOREIGN KEY (tag1) REFERENCES tags(id),
            FOREIGN KEY (tag2) REFERENCES tags(id),
            FOREIGN KEY (tag3) REFERENCES tags(id),
            FOREIGN KEY (tag4) REFERENCES tags(id),
            FOREIGN KEY (tag5) REFERENCES tags(id),
            FOREIGN KEY (tag6) REFERENCES tags(id),
            FOREIGN KEY (tag7) REFERENCES tags(id),
            FOREIGN KEY (tag8) REFERENCES tags(id),
            FOREIGN KEY (tag9) REFERENCES tags(id),
            FOREIGN KEY (tag10) REFERENCES tags(id)
        )
    ");

    echo "2. group_post_images テーブルを作成...\n";

    // グループ投稿内の画像テーブル
    $db->exec("
        CREATE TABLE IF NOT EXISTS group_post_images (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            group_post_id INTEGER NOT NULL,
            image_path TEXT NOT NULL,
            thumb_path TEXT,
            display_order INTEGER DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (group_post_id) REFERENCES group_posts(id) ON DELETE CASCADE
        )
    ");

    echo "3. インデックスを作成...\n";

    // group_post_idでの検索を高速化
    $db->exec("CREATE INDEX IF NOT EXISTS idx_group_post_images_group_id ON group_post_images(group_post_id)");

    // 表示順序でのソートを高速化
    $db->exec("CREATE INDEX IF NOT EXISTS idx_group_post_images_order ON group_post_images(group_post_id, display_order)");

    // 公開投稿の検索を高速化
    $db->exec("CREATE INDEX IF NOT EXISTS idx_group_posts_visible ON group_posts(is_visible, created_at DESC)");

    echo "✓ マイグレーション完了\n\n";
    echo "作成されたテーブル:\n";
    echo "  - group_posts: グループ投稿のメタデータ（タイトル、タグ、NSFWフラグなど）\n";
    echo "  - group_post_images: グループ内の画像（画像パス、表示順序）\n";
    echo "\n";
    echo "設計のメリット:\n";
    echo "  - postsテーブルは単独投稿専用として独立\n";
    echo "  - グループ投稿は完全に分離された機能\n";
    echo "  - 管理画面でも明確に区別可能\n";

} catch (Exception $e) {
    echo "✗ エラー: " . $e->getMessage() . "\n";
    exit(1);
}
