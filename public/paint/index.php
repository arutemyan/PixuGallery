<?php
/**
 * Paint Gallery - イラスト一覧ページ
 * public/paint/index.php
 */

declare(strict_types=1);

// 共通ブートストラップ
require_once(__DIR__ . '/../../bootstrap.php');

use App\View\View;
use App\Utils\Logger;

// ページ固有の設定
$siteTitle = $theme['site_title'] ?? 'ペイントギャラリー';
$siteSubtitle = $theme['site_subtitle'] ?? 'キャンバスで描いたオリジナルイラスト作品集';

$pageTitle = 'ペイントギャラリー';
$pageDescription = 'オリジナルイラスト作品ギャラリー';
$bodyAttributes = sprintf(
    'data-age-verification-minutes="%s" data-nsfw-config-version="%s"',
    $ageVerificationMinutes,
    $nsfwConfigVersion
);

// 戻るボタンを表示
$showBackButton = true;
$backButtonUrl = '/index.php';

// 追加CSS
$additionalCss = ['/paint/css/gallery.css'];

// JavaScript ファイル
$additionalJs = ['/paint/js/gallery.js'];

// Viewでレンダリング
View::render('paint-gallery', [
    'pageTitle' => $pageTitle,
    'pageDescription' => $pageDescription,
    'bodyAttributes' => $bodyAttributes,
    'showBackButton' => $showBackButton,
    'backButtonUrl' => $backButtonUrl,
    'additionalCss' => $additionalCss,
    'additionalJs' => $additionalJs,
    'theme' => $theme,
    'ageVerificationMinutes' => $ageVerificationMinutes,
    'nsfwConfigVersion' => $nsfwConfigVersion,
]);
