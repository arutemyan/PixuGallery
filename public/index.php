<?php

declare(strict_types=1);

// 共通ブートストラップ
require_once __DIR__ . '/../bootstrap.php';

use App\Models\Post;
use App\Models\Tag;
use App\View\View;
use App\Utils\Logger;

// セットアップチェック
try {
    $db = \App\Database\Connection::getInstance();
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM users");
    $stmt->execute();
    $result = $stmt->fetch();
    if ($result['count'] == 0) {
        exit(0);
    }
} catch (Exception $e) {
    Logger::getInstance()->error('Setup check error: ' . $e->getMessage());
}

try {
    // 統一されたPostモデルで全投稿を取得（シングル・グループ両方）
    $postModel = new Post();
    $posts = $postModel->getAllUnified(18, 'all', null, 0);

    // post_typeを文字列に変換（互換性のため）
    foreach ($posts as &$post) {
        $post['post_type'] = $post['post_type'] == 1 ? 'group' : 'single';
    }

    // タグ一覧を取得（ID, name, post_count）
    $tagModel = new Tag();
    $tags = $tagModel->getPopular(50); // 上位50件のタグ

} catch (Exception $e) {
    Logger::getInstance()->error('Index Error: ' . $e->getMessage());
    $posts = [];
    $tags = [];
}

// タグデータをJavaScriptに渡す
$inlineScripts = [
    'const TAGS_DATA = ' . json_encode($tags, JSON_UNESCAPED_UNICODE) . ';'
];

// ページ固有の設定
$pageTitle = $theme['site_title'] ?? 'イラストポートフォリオ';
$pageDescription = $theme['site_description'] ?? 'イラストレーターのポートフォリオサイト';
$bodyAttributes = sprintf(
    'data-age-verification-minutes="%s" data-nsfw-config-version="%s"',
    $ageVerificationMinutes,
    $nsfwConfigVersion
);

// JavaScript ファイル
$additionalJs = ['/res/js/main.js'];

// Viewでレンダリング
View::render('gallery', [
    'posts' => $posts,
    'tags' => $tags,
    'paintEnabled' => $paintEnabled,
    'pageTitle' => $pageTitle,
    'pageDescription' => $pageDescription,
    'bodyAttributes' => $bodyAttributes,
    'additionalJs' => $additionalJs,
    'inlineScripts' => $inlineScripts,
    'theme' => $theme,
    'showViewCount' => $showViewCount,
    'ageVerificationMinutes' => $ageVerificationMinutes,
    'nsfwConfigVersion' => $nsfwConfigVersion,
    'ogpTitle' => $ogpTitle,
    'ogpDescription' => $ogpDescription,
    'ogpImageUrl' => $ogpImageUrl,
    'twitterCard' => $twitterCard,
    'twitterSite' => $twitterSite,
]);
