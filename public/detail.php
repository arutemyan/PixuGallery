<?php

declare(strict_types=1);

// 共通ブートストラップ
require_once __DIR__ . '/../bootstrap.php';

use App\Models\Post;
use App\Models\GroupPostImage;
use App\View\View;
use App\Utils\Logger;

// パラメータの検証
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: /index.php');
    exit;
}
if (!isset($_GET['viewtype']) || !is_numeric($_GET['viewtype'])) {
    header('Location: /index.php');
    exit;
}
$id = (int)$_GET['id'];
$type = (int)$_GET['viewtype'];
if (!(0 <= $type && $type <= 1)) {
    header('Location: /index.php');
    exit;
}
$isGroupPost = ($type === 1);

try {
    // 投稿を取得（統一されたPostモデルを使用）
    $model = new Post();
    $data = $model->getById($id);

    if ($data === null) {
        header('Location: /index.php');
        exit;
    }

    // post_typeが一致するか確認
    if ($data['post_type'] != $type) {
        header('Location: /index.php');
        exit;
    }

    // グループ投稿の場合は画像を取得
    if ($isGroupPost) {
        $groupPostImageModel = new GroupPostImage();
        $data['images'] = $groupPostImageModel->getImagesByPostId($id);
    }

    // 閲覧数をインクリメント
    // Visitor ID を発行/取得して渡す（DB 側の重複抑止に使用）
    $visitorId = \App\Security\VisitorIdHelper::getOrCreate();
    $model->incrementViewCount($id, $visitorId);

} catch (Exception $e) {
    Logger::getInstance()->error('Post Detail Error (' . $type . '): ' . $e->getMessage());
    header('Location: /index.php');
    exit;
}

// SNS共有用の画像パスを決定
$isSensitive = isset($data['is_sensitive']) && $data['is_sensitive'] == 1;
$shareImagePath = '';

function getNsfwImagePathForDetail($imagePath) {
    $pathInfo = pathinfo($imagePath);
    $nsfwFilename = basename($pathInfo['filename'] . '_nsfw.' . ($pathInfo['extension'] ?? 'webp'));
    return $pathInfo['dirname'] . '/' . $nsfwFilename;
}

if ($isGroupPost) {
    // グループ投稿の場合：最初の画像のサムネイル
    if (!empty($data['images']) && !empty($data['images'][0]['thumb_path'])) {
        $shareImagePath = $data['images'][0]['thumb_path'];

        if ($isSensitive) {
            $shareImagePath = getNsfwImagePathForDetail($shareImagePath);
        }
    }
} else {
    // 単一投稿の場合
    if (!empty($data['image_path'])) {
        if ($isSensitive) {
            // NSFW画像の場合はNSFWフィルター版を使用
            $shareImagePath = getNsfwImagePathForDetail($data['image_path']);

            // パスの検証（uploadsディレクトリ内であることを確認）
            $fullPath = realpath(__DIR__ . '/' . $shareImagePath);
            $uploadsDir = realpath(__DIR__ . '/uploads/');

            // NSFWフィルター版が存在しない、または不正なパスの場合はサムネイルのNSFWフィルター版を使用
            if (!$fullPath || !$uploadsDir || strpos($fullPath, $uploadsDir) !== 0 || !file_exists($fullPath)) {
                if (!empty($data['thumb_path'])) {
                    $shareImagePath = getNsfwImagePathForDetail($data['thumb_path']);
                } else {
                    $shareImagePath = '';
                }
            }
        } else {
            // 通常の画像はサムネイルを使用
            $shareImagePath = $data['thumb_path'] ?? $data['image_path'];
        }
    }
}

$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
$fullUrl = $protocol . ($_SERVER['HTTP_HOST'] ?? 'localhost') . $_SERVER['REQUEST_URI'];
$imageUrl = !empty($shareImagePath) ? $protocol . ($_SERVER['HTTP_HOST'] ?? 'localhost') . '/' . $shareImagePath : '';

// OGP設定
$ogp = [
    'title' => $data['title'],
    'type' => 'article',
    'url' => $fullUrl,
    'description' => mb_substr($data['detail'] ?? $data['title'], 0, 200),
    'image' => $imageUrl,
    'site_name' => $theme['site_title'] ?? 'イラストポートフォリオ',
    'twitter_card' => 'summary_large_image',
];

// ページ固有の設定
$pageTitle = $data['title'] . ' - ' . ($theme['site_title'] ?? 'イラストポートフォリオ');
$pageDescription = mb_substr($data['detail'] ?? $data['title'], 0, 200);
$bodyAttributes = sprintf(
    'data-age-verification-minutes="%s" data-nsfw-config-version="%s" data-post-id="%s" data-is-sensitive="%s"',
    $ageVerificationMinutes,
    $nsfwConfigVersion,
    $id,
    $isSensitive ? '1' : '0'
);

// 戻るボタンを表示
$showBackButton = true;
$backButtonUrl = '/index.php';

// JavaScript ファイル
$additionalJs = ['/res/js/detail.js'];

// グループ投稿用のJavaScript
$groupGalleryScript = '';
if ($isGroupPost) {
    $groupGalleryScript = <<<'JS'
let currentImageIndex = 0;
const images = document.querySelectorAll('.gallery-image');
const totalImages = images.length;

function showImage(index) {
    images.forEach((img, i) => {
        img.classList.toggle('active', i === index);
    });
    document.getElementById('currentImageIndex').textContent = index + 1;
    currentImageIndex = index;
}

function nextImage() {
    const nextIndex = (currentImageIndex + 1) % totalImages;
    showImage(nextIndex);
}

function previousImage() {
    const prevIndex = (currentImageIndex - 1 + totalImages) % totalImages;
    showImage(prevIndex);
}

// キーボードナビゲーション
document.addEventListener('keydown', (e) => {
    if (e.key === 'ArrowRight') nextImage();
    if (e.key === 'ArrowLeft') previousImage();
});
JS;
}

// SNS共有用のJavaScript
$snsShareScript = sprintf(
    <<<'JS'
// SNS共有機能
function shareToSNS(platform) {
    const title = %s;
    const url = encodeURIComponent(window.location.href);
    const encodedTitle = encodeURIComponent(title);
    const hashtags = 'イラスト,artwork';
    const isSensitive = %s;
    const nsfwHashtag = isSensitive ? ',NSFW' : '';
    const fullHashtags = encodeURIComponent(hashtags + nsfwHashtag);

    let shareUrl;
    if (platform === 'twitter') {
        shareUrl = `https://twitter.com/intent/tweet?url=${url}&text=${encodedTitle}&hashtags=${fullHashtags}`;
    } else if (platform === 'misskey') {
        shareUrl = `https://misskey-hub.net/share/?text=${encodedTitle}%%20${url}`;
    }

    if (shareUrl) {
        window.open(shareUrl, '_blank', 'width=600,height=400');
    }
}

// URLコピー機能
function copyPageUrl() {
    const url = window.location.href;
    navigator.clipboard.writeText(url).then(() => {
        alert('URLをコピーしました！');
    }).catch(err => {
        console.error('コピーに失敗しました:', err);
    });
}
JS,
    json_encode($data['title']),
    $isSensitive ? 'true' : 'false'
);

// 詳細ページ初期化スクリプト
$detailInitScript = sprintf(
    <<<'JS'
// DOMロード後に初期化
document.addEventListener('DOMContentLoaded', function() {
    // 年齢確認チェック
    initDetailPage(%s, %s);
});
JS,
    $isSensitive ? 'true' : 'false',
    $type
);

$inlineScripts = array_filter([$groupGalleryScript, $snsShareScript, $detailInitScript]);

// Viewでレンダリング
View::render('detail', [
    'data' => $data,
    'isGroupPost' => $isGroupPost,
    'pageTitle' => $pageTitle,
    'pageDescription' => $pageDescription,
    'bodyAttributes' => $bodyAttributes,
    'ogp' => $ogp,
    'showBackButton' => $showBackButton,
    'backButtonUrl' => $backButtonUrl,
    'additionalJs' => $additionalJs,
    'inlineScripts' => $inlineScripts,
    'theme' => $theme,
    'showViewCount' => $showViewCount,
    'ageVerificationMinutes' => $ageVerificationMinutes,
    'nsfwConfigVersion' => $nsfwConfigVersion,
    'twitterSite' => $twitterSite,
]);
