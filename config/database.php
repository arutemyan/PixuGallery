<?php

declare(strict_types=1);

/**
 * データベース設定ファイル
 *
 * 3層DB構成でデータを管理:
 * - gallery.db: メインコンテンツ（投稿、ユーザー、テーマなど）
 * - counters.db: 閲覧数などの頻繁に更新されるカウンター
 * - access_logs.db: アクセスログ（オプション機能、設定でON/OFF可能）
 */

return [
    // メインデータベース（ギャラリーコンテンツ）
    'gallery' => [
        'path' => __DIR__ . '/../data/gallery.db',
        'description' => 'Main gallery content (posts, users, themes, settings)',
    ],

    // カウンターデータベース（閲覧数など）
    'counters' => [
        'path' => __DIR__ . '/../data/counters.db',
        'description' => 'View counts and other frequently updated counters',
    ],

    // アクセスログデータベース（オプション）
    'access_logs' => [
        'path' => __DIR__ . '/../data/access_logs.db',
        'description' => 'Access logs (IP, UserAgent, Referer, etc.)',
        'enabled' => false, // デフォルトは無効
    ],

    // データベースディレクトリのパーミッション
    'directory_permission' => 0755,

    // PDO接続オプション
    'pdo_options' => [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ],

    // 後方互換性のため（非推奨）
    'database_path' => __DIR__ . '/../data/gallery.db',
];
