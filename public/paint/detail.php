<?php
/**
 * Paint Detail - イラスト詳細ページ
 * public/paint/detail.php
 */

declare(strict_types=1);

// 共通ブートストラップ
require_once(__DIR__ . '/../../bootstrap.php');

use App\View\View;
use App\Utils\Logger;

// パラメータ取得
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id <= 0) {
    header('Location: /paint/');
    exit;
}

try {
    $db = \App\Database\Connection::getInstance();

    // 管理者権限チェック
    session_start();
    $isAdmin = isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);

    // イラスト情報取得
    $sql = "SELECT
                i.id,
                i.title,
                '' as detail,
                i.image_path,
                i.thumbnail_path as thumb_path,
                i.data_path,
                i.timelapse_path,
                i.timelapse_size,
                i.nsfw,
                i.is_visible,
                i.canvas_width as width,
                i.canvas_height as height,
                i.created_at,
                i.updated_at,
                '' as tags
            FROM paint i
            WHERE i.id = :id";

    $stmt = $db->prepare($sql);
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    $stmt->execute();

    $illust = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$illust) {
        header('Location: /paint/');
        exit;
    }

    // 非表示チェック（管理者以外は非表示を見れない）
    if (!$isAdmin && isset($illust['is_visible']) && $illust['is_visible'] == 0) {
        header('Location: /paint/');
        exit;
    }

    // 関連イラストを取得
    $relatedSql = "SELECT
                    i.id,
                    i.title,
                    i.thumbnail_path as thumb_path,
                    i.image_path
                FROM paint i
                WHERE i.id != ?
                ORDER BY i.created_at DESC
                LIMIT 6";

    $relatedStmt = $db->prepare($relatedSql);
    $relatedStmt->bindValue(1, $id, PDO::PARAM_INT);
    $relatedStmt->execute();
    $relatedIllusts = $relatedStmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    Logger::getInstance()->error('Paint Detail Error: ' . $e->getMessage());
    header('Location: /paint/');
    exit;
}

// OGP用の画像URL
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$imageUrl = $protocol . $host . ($illust['thumb_path'] ?? $illust['image_path']);
$pageUrl = $protocol . $host . $_SERVER['REQUEST_URI'];

$siteTitle = $theme['site_title'] ?? 'ペイントギャラリー';

// OGP設定
$ogp = [
    'title' => $illust['title'],
    'type' => 'article',
    'url' => $pageUrl,
    'image' => $imageUrl,
    'description' => mb_substr($illust['detail'] ?? $illust['title'], 0, 200),
    'twitter_card' => 'summary_large_image',
];

// ページ固有の設定
$pageTitle = $illust['title'] . ' - ' . $siteTitle;
$pageDescription = mb_substr($illust['detail'] ?? $illust['title'], 0, 200);
$bodyAttributes = sprintf(
    'data-age-verification-minutes="%s" data-nsfw-config-version="%s" data-is-nsfw="%s"',
    $ageVerificationMinutes,
    $nsfwConfigVersion,
    !empty($illust['nsfw']) ? '1' : '0'
);

// 戻るボタンを表示
$showBackButton = true;
$backButtonUrl = '/paint/';

// 追加CSS
$additionalCss = ['/paint/css/gallery.css', '/paint/css/detail.css'];

// JavaScript ファイル
$additionalJs = ['/paint/js/detail.js'];

// 年齢確認スクリプト
$ageVerificationScript = <<<'JS'
// 年齢確認関数
function checkAgeVerification() {
    const verified = localStorage.getItem('age_verified');
    const storedVersion = localStorage.getItem('age_verified_version');
    const currentVersion = String(NSFW_CONFIG_VERSION);

    if (!storedVersion || storedVersion !== currentVersion) {
        localStorage.removeItem('age_verified');
        localStorage.removeItem('age_verified_version');
        return false;
    }

    if (!verified) return false;

    const verifiedTime = parseInt(verified);
    const now = Date.now();
    const expiryMs = AGE_VERIFICATION_MINUTES * 60 * 1000;
    return (now - verifiedTime) < expiryMs;
}

function setAgeVerification() {
    localStorage.setItem('age_verified', Date.now().toString());
    localStorage.setItem('age_verified_version', String(NSFW_CONFIG_VERSION));
}

function showAgeVerificationModal() {
    const modal = document.getElementById('ageVerificationModal');
    if (modal) modal.classList.add('show');
}

function hideAgeVerificationModal() {
    const modal = document.getElementById('ageVerificationModal');
    if (modal) modal.classList.remove('show');
}

function confirmAge() {
    setAgeVerification();
    hideAgeVerificationModal();
    // ページをリロードして画像を表示
    window.location.reload();
}

function denyAge() {
    hideAgeVerificationModal();
    // ギャラリーに戻る
    window.location.href = '/paint/';
}

// ページ読み込み時の処理
document.addEventListener('DOMContentLoaded', () => {
    const isNsfw = document.body.dataset.isNsfw === '1';

    if (isNsfw && !checkAgeVerification()) {
        // NSFW画像で年齢確認が済んでいない場合、モーダルを表示
        showAgeVerificationModal();
    }
});
JS;

$inlineScripts = [$ageVerificationScript];

// Viewでレンダリング
View::render('paint-detail', [
    'illust' => $illust,
    'relatedIllusts' => $relatedIllusts,
    'pageTitle' => $pageTitle,
    'pageDescription' => $pageDescription,
    'bodyAttributes' => $bodyAttributes,
    'ogp' => $ogp,
    'showBackButton' => $showBackButton,
    'backButtonUrl' => $backButtonUrl,
    'additionalCss' => $additionalCss,
    'additionalJs' => $additionalJs,
    'inlineScripts' => $inlineScripts,
    'theme' => $theme,
    'ageVerificationMinutes' => $ageVerificationMinutes,
    'nsfwConfigVersion' => $nsfwConfigVersion,
    'twitterSite' => $twitterSite,
]);
