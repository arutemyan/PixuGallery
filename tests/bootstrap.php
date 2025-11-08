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

// テスト環境用: 環境変数により DB 設定を上書きできるようにする
// CI では TEST_DB_DRIVER/TEST_DB_DSN/TEST_DB_HOST/... を設定してテスト実行します
if (getenv('TEST_DB_DRIVER') !== false) {
    try {
        // ConfigManager を取得して内部設定を上書きする
        $cmClass = '\App\Config\ConfigManager';
        if (class_exists($cmClass)) {
            $cm = $cmClass::getInstance();

            // 既存設定を取得
            $reflect = new \ReflectionClass($cmClass);
            $prop = $reflect->getProperty('config');
            $prop->setAccessible(true);
            $cfg = $prop->getValue($cm);

            // 適用する環境変数を読み取る
            $driver = getenv('TEST_DB_DRIVER') ?: null;
            $dsn = getenv('TEST_DB_DSN') ?: null;
            $host = getenv('TEST_DB_HOST') ?: null;
            $port = getenv('TEST_DB_PORT') ?: null;
            $dbname = getenv('TEST_DB_NAME') ?: null;
            $user = getenv('TEST_DB_USER') ?: null;
            $pass = getenv('TEST_DB_PASS') ?: null;

            if ($driver) {
                $cfg['database']['driver'] = $driver;

                if ($driver === 'sqlite' && $dsn) {
                    // DSN 形式 (sqlite:/path or sqlite::memory:) の path 部分を取り出す
                    if (preg_match('#^sqlite:(.*)$#', $dsn, $m)) {
                        $path = $m[1];
                        $cfg['database']['sqlite']['gallery']['path'] = $path;
                    } else {
                        // 直接パス指定とみなす
                        $cfg['database']['sqlite']['gallery']['path'] = $dsn;
                    }
                }

                if (($driver === 'mysql' || $driver === 'postgresql')) {
                    if ($host) $cfg['database'][$driver === 'mysql' ? 'mysql' : 'postgresql']['host'] = $host;
                    if ($port) $cfg['database'][$driver === 'mysql' ? 'mysql' : 'postgresql']['port'] = (int)$port;
                    if ($dbname) $cfg['database'][$driver === 'mysql' ? 'mysql' : 'postgresql']['database'] = $dbname;
                    if ($user) $cfg['database'][$driver === 'mysql' ? 'mysql' : 'postgresql']['username'] = $user;
                    if ($pass) $cfg['database'][$driver === 'mysql' ? 'mysql' : 'postgresql']['password'] = $pass;
                }
            }

            // 上書き反映
            $prop->setValue($cm, $cfg);
        }
    } catch (\Throwable $e) {
        // ブートストラップ内では致命的にしない
        // テスト実行時にログを出すために標準出力へ
        fwrite(STDERR, "Warning: could not apply TEST_DB_* overrides: " . $e->getMessage() . "\n");
    }
}

// テスト用の定数設定
define('TEST_ENV', true);
define('CACHE_DIR', PROJECT_ROOT . '/cache');
define('DATA_DIR', PROJECT_ROOT . '/data');

// エラーレポーティングの設定
error_reporting(E_ALL);
ini_set('display_errors', '1');

// タイムゾーン設定
date_default_timezone_set('Asia/Tokyo');
