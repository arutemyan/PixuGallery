<?php
/**
 * PHPUnit Bootstrap File
 * テスト実行前に読み込まれる初期化ファイル
 */

declare(strict_types=1);

// プロジェクトルートディレクトリを定義
define('PROJECT_ROOT', dirname(__DIR__));

// オートローダーの設定（Composerを使用する場合）
if (file_exists(PROJECT_ROOT . '/vendor/autoload.php')) {
    require_once PROJECT_ROOT . '/vendor/autoload.php';
}

// テスト用の定数設定
define('TEST_ENV', true);
// テストでのデフォルトキャッシュディレクトリを config/PathHelper 経由で取得
define('CACHE_DIR', \App\Utils\PathHelper::getCacheDir());
define('DATA_DIR', \App\Utils\PathHelper::getDataDir());

// エラーレポーティングの設定
error_reporting(E_ALL);
ini_set('display_errors', '1');

// タイムゾーン設定
date_default_timezone_set('Asia/Tokyo');
