<?php
/**
 * アプリケーション共通ブートストラップ
 *
 * 全ページで共通の初期化処理を実行します。
 * public/index.php, public/detail.php, public/paint/*.php などから require されます。
 */

declare(strict_types=1);

// Composer オートローダー
require_once __DIR__ . '/vendor/autoload.php';

// セキュリティユーティリティ
require_once __DIR__ . '/src/Security/SecurityUtil.php';

// 設定読み込み
$config = \App\Config\ConfigManager::getInstance()->getConfig();

// メンテナンスモードの強制表示（ページ向け）
try {
    if (class_exists('\App\\Utils\\Maintenance')) {
        \App\Utils\Maintenance::enforceForPages();
    }
} catch (\Throwable $e) {
    // ここでは何もしない — メンテナンス判定が失敗しても通常動作を継続
}

// タイムゾーン設定
date_default_timezone_set('Asia/Tokyo');

// 共通で使用するモデルやサービスの初期化
use App\Models\Theme;
use App\Models\Setting;
use App\Utils\Logger;

// グローバルエラーハンドラー（必要に応じて）
if ($config['app']['environment'] === 'production') {
    error_reporting(0);
    ini_set('display_errors', '0');
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
}

/**
 * 共通初期化データの準備
 * 全ページで使用するテーマ設定・NSFW設定などを取得
 */
try {
    // テーマ設定を取得
    $themeModel = new Theme();
    $theme = $themeModel->getCurrent();

    // サイト設定を取得
    $settingModel = new Setting();
    $showViewCount = $settingModel->get('show_view_count', '1') === '1';

    // NSFW設定を取得
    $nsfwConfig = $config['nsfw'];
    $ageVerificationMinutes = $nsfwConfig['age_verification_minutes'];
    $nsfwConfigVersion = $nsfwConfig['config_version'];

    // OGP設定を取得
    $ogpTitle = $settingModel->get('ogp_title', '') ?: ($theme['site_title'] ?? '');
    $ogpDescription = $settingModel->get('ogp_description', '') ?: ($theme['site_description'] ?? '');
    $ogpImage = $settingModel->get('ogp_image', '');
    $twitterCard = $settingModel->get('twitter_card', 'summary_large_image');
    $twitterSite = $settingModel->get('twitter_site', '');

    // OGP画像の絶対URLを生成
    $ogpImageUrl = '';
    if ($ogpImage) {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $ogpImageUrl = $protocol . '://' . $host . '/' . $ogpImage;
    }

    // 機能フラグ
    $paintEnabled = \App\Utils\FeatureGate::isEnabled('paint');

} catch (Exception $e) {
    Logger::getInstance()->error('Bootstrap Error: ' . $e->getMessage());

    // デフォルト値を設定
    $theme = [
        'site_title' => 'イラストポートフォリオ',
        'site_description' => 'イラストレーターのポートフォリオサイト',
        'header_html' => '',
        'footer_html' => '',
    ];
    $showViewCount = true;
    $ageVerificationMinutes = 10080;
    $nsfwConfigVersion = 1;
    $ogpTitle = $theme['site_title'];
    $ogpDescription = $theme['site_description'];
    $ogpImage = '';
    $ogpImageUrl = '';
    $twitterCard = 'summary_large_image';
    $twitterSite = '';
    $paintEnabled = true;
}
