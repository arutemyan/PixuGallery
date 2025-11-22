<?php
/**
 * 公開サイト用メインレイアウト（完全統合版）
 *
 * $content: ページ固有のコンテンツ（バッファリング済み）
 * $pageTitle: ページタイトル
 * $pageDescription: ページ説明
 * $ogp: OGP設定（配列）
 * $bodyAttributes: body タグに追加する属性
 * $showBackButton: 戻るボタンを表示するか
 * $backButtonUrl: 戻るボタンのURL
 * $additionalCss: 追加CSS（配列）
 * $additionalJs: 追加JS（配列）
 */
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= escapeHtml($pageTitle ?? $theme['site_title'] ?? 'イラストポートフォリオ') ?></title>
    <meta name="description" content="<?= escapeHtml($pageDescription ?? $theme['site_description'] ?? 'イラストレーターのポートフォリオサイト') ?>">

    <!-- OGP (Open Graph Protocol) -->
    <?php if (isset($ogp)): ?>
    <meta property="og:title" content="<?= escapeHtml($ogp['title'] ?? $pageTitle ?? $theme['site_title'] ?? '') ?>">
    <meta property="og:type" content="<?= escapeHtml($ogp['type'] ?? 'website') ?>">
    <meta property="og:description" content="<?= escapeHtml($ogp['description'] ?? $pageDescription ?? $theme['site_description'] ?? '') ?>">
    <meta property="og:url" content="<?= escapeHtml($ogp['url'] ?? '') ?>">
    <?php if (!empty($ogp['image'])): ?>
    <meta property="og:image" content="<?= escapeHtml($ogp['image']) ?>">
    <?php endif; ?>
    <?php if (!empty($ogp['site_name'])): ?>
    <meta property="og:site_name" content="<?= escapeHtml($ogp['site_name']) ?>">
    <?php endif; ?>
    <?php else: ?>
    <!-- デフォルトOGP -->
    <meta property="og:title" content="<?= escapeHtml($ogpTitle ?? $theme['site_title'] ?? '') ?>">
    <meta property="og:type" content="website">
    <meta property="og:description" content="<?= escapeHtml($ogpDescription ?? $theme['site_description'] ?? '') ?>">
    <meta property="og:url" content="<?= htmlspecialchars((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . $_SERVER['REQUEST_URI'], ENT_QUOTES) ?>">
    <?php if (!empty($ogpImageUrl)): ?>
    <meta property="og:image" content="<?= escapeHtml($ogpImageUrl) ?>">
    <?php endif; ?>
    <?php endif; ?>

    <!-- Twitter Card -->
    <?php if (isset($ogp)): ?>
    <meta name="twitter:card" content="<?= escapeHtml($ogp['twitter_card'] ?? 'summary_large_image') ?>">
    <meta name="twitter:title" content="<?= escapeHtml($ogp['title'] ?? $pageTitle ?? '') ?>">
    <meta name="twitter:description" content="<?= escapeHtml($ogp['description'] ?? $pageDescription ?? '') ?>">
    <?php if (!empty($ogp['image'])): ?>
    <meta name="twitter:image" content="<?= escapeHtml($ogp['image']) ?>">
    <?php endif; ?>
    <?php if (!empty($twitterSite)): ?>
    <meta name="twitter:site" content="@<?= escapeHtml($twitterSite) ?>">
    <?php endif; ?>
    <?php else: ?>
    <meta name="twitter:card" content="<?= escapeHtml($twitterCard ?? 'summary_large_image') ?>">
    <?php if (!empty($twitterSite)): ?>
    <meta name="twitter:site" content="@<?= escapeHtml($twitterSite) ?>">
    <?php endif; ?>
    <meta name="twitter:title" content="<?= escapeHtml($ogpTitle ?? $pageTitle ?? $theme['site_title'] ?? '') ?>">
    <meta name="twitter:description" content="<?= escapeHtml($ogpDescription ?? $pageDescription ?? $theme['site_description'] ?? '') ?>">
    <?php if (!empty($ogpImageUrl)): ?>
    <meta name="twitter:image" content="<?= escapeHtml($ogpImageUrl) ?>">
    <?php endif; ?>
    <?php endif; ?>

    <!-- CSS -->
    <link href="https://fonts.googleapis.com/css2?family=Noto+Serif+JP:wght@400;700&display=swap" rel="stylesheet">
    <?php if (!empty($additionalCssFirst)): ?>
        <?php foreach ($additionalCssFirst as $css): ?>
            <?= \App\Utils\AssetHelper::linkTag($css) ?>
        <?php endforeach; ?>
    <?php endif; ?>
    <?= \App\Utils\AssetHelper::linkTag('/res/css/main.css') ?>
    <?= \App\Utils\AssetHelper::linkTag('/res/css/inline-styles.css') ?>
    <?php if (!empty($additionalCss)): ?>
        <?php foreach ($additionalCss as $css): ?>
            <?= \App\Utils\AssetHelper::linkTag($css) ?>
        <?php endforeach; ?>
    <?php endif; ?>

    <!-- 動的テーマカラー（CSS変数） -->
    <style>
    :root {
        --primary-color: <?= escapeHtml($theme['primary_color'] ?? '#8B5AFA') ?>;
        --secondary-color: <?= escapeHtml($theme['secondary_color'] ?? '#667eea') ?>;
        --accent-color: <?= escapeHtml($theme['accent_color'] ?? '#FFD700') ?>;
        --background-color: <?= escapeHtml($theme['background_color'] ?? '#1a1a1a') ?>;
        --text-color: <?= escapeHtml($theme['text_color'] ?? '#ffffff') ?>;
        --heading-color: <?= escapeHtml($theme['heading_color'] ?? '#ffffff') ?>;
        --footer-bg-color: <?= escapeHtml($theme['footer_bg_color'] ?? '#2a2a2a') ?>;
        --footer-text-color: <?= escapeHtml($theme['footer_text_color'] ?? '#cccccc') ?>;
        --card-border-color: <?= escapeHtml($theme['card_border_color'] ?? '#333333') ?>;
        --card-bg-color: <?= escapeHtml($theme['card_bg_color'] ?? '#252525') ?>;
        --card-shadow-opacity: <?= escapeHtml($theme['card_shadow_opacity'] ?? '0.3') ?>;
        --link-color: <?= escapeHtml($theme['link_color'] ?? '#8B5AFA') ?>;
        --link-hover-color: <?= escapeHtml($theme['link_hover-color'] ?? '#a177ff') ?>;
        --tag-bg-color: <?= escapeHtml($theme['tag_bg_color'] ?? '#8B5AFA') ?>;
        --tag-text-color: <?= escapeHtml($theme['tag_text_color'] ?? '#ffffff') ?>;
        --filter-active-bg-color: <?= escapeHtml($theme['filter_active_bg_color'] ?? '#8B5AFA') ?>;
        --filter-active-text-color: <?= escapeHtml($theme['filter_active_text_color'] ?? '#ffffff') ?>;
    }
    <?php if (!empty($theme['header_image'])): ?>
    header {
        background-image: url('/<?= escapeHtml($theme['header_image']) ?>');
        background-size: cover;
        background-blend-mode: overlay;
        background-position: left top;
        background-repeat: no-repeat;
    }
    <?php endif; ?>
    </style>
</head>
<body <?= $bodyAttributes ?? '' ?>>
    <!-- 年齢確認モーダル -->
    <?php
    // 表示時間の計算
    $displayTime = '';
    if (isset($ageVerificationMinutes)) {
        if ($ageVerificationMinutes < 60) {
            $displayTime = $ageVerificationMinutes . '分間';
        } elseif ($ageVerificationMinutes < 1440) {
            $displayTime = round($ageVerificationMinutes / 60, 1) . '時間';
        } else {
            $displayTime = round($ageVerificationMinutes / 1440, 1) . '日間';
        }
    }
    ?>
    <div id="ageVerificationModal" class="modal">
        <div class="modal-dialog">
            <div class="modal-header">
                <h2 class="modal-title">年齢確認</h2>
                <button type="button" class="modal-close" onclick="denyAge()">&times;</button>
            </div>
            <div class="modal-body">
                <p>このコンテンツは18歳未満の閲覧に適さない可能性があります。</p>
                <p><strong>あなたは18歳以上ですか？</strong></p>
                <?php if ($displayTime): ?>
                <p class="muted-small">
                    ※一度確認すると、ブラウザに記録され一定期間（<?= $displayTime ?>）は再度確認されません。<br>
                    記録を削除したい場合はブラウザのCookieを削除してください。
                </p>
                <?php endif; ?>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="denyAge()">いいえ</button>
                <button type="button" class="btn btn-primary" onclick="confirmAge()">はい、18歳以上です</button>
            </div>
        </div>
    </div>

    <!-- ヘッダー -->
    <header>
        <?php if (!empty($theme['logo_image'])): ?>
            <img src="/<?= escapeHtml($theme['logo_image']) ?>" alt="<?= escapeHtml($theme['site_title'] ?? 'ロゴ') ?>" class="img-logo">
        <?php endif; ?>
        <h1><?= !empty($theme['header_html']) ? escapeHtml($theme['header_html']) : escapeHtml($theme['site_title'] ?? 'イラストポートフォリオ') ?></h1>

        <?php if (!empty($theme['site_subtitle'])): ?>
            <p><?= escapeHtml($theme['site_subtitle']) ?></p>
        <?php endif; ?>
    </header>

    <?php if (!empty($showBackButton)): ?>
    <?php
    // 一覧に戻るボタンの設定
    $backButtonText = $theme['back_button_text'] ?? '一覧に戻る';
    $backButtonUrl = $backButtonUrl ?? '/index.php';
    ?>
    <a href="<?= escapeHtml($backButtonUrl) ?>" class="back-link">
        <div class="header-back-button header-back-button-inline">
            <?= escapeHtml($backButtonText) ?>
        </div>
    </a>
    <?php endif; ?>

    <!-- メインコンテンツ -->
    <?= $content ?>

    <!-- フッター -->
    <footer>
        <p><?= !empty($theme['footer_html']) ? nl2br(escapeHtml($theme['footer_html'])) : '&copy; ' . date('Y') . ' イラストポートフォリオ. All rights reserved.' ?></p>
    </footer>

    <!-- 年齢確認・NSFW設定のグローバル変数 -->
    <?php if (isset($ageVerificationMinutes) && isset($nsfwConfigVersion)): ?>
    <script nonce="<?= \App\Security\CspMiddleware::getInstance()->getNonce() ?>">
        const AGE_VERIFICATION_MINUTES = <?= (float)$ageVerificationMinutes ?>;
        const NSFW_CONFIG_VERSION = <?= (int)$nsfwConfigVersion ?>;
    </script>
    <?php endif; ?>

    <!-- グローバル: アップロードURL と プレースホルダーURL (フロントエンドが参照するため) -->
    <script nonce="<?= \App\Security\CspMiddleware::getInstance()->getNonce() ?>">
        window.PLACEHOLDER_URL = <?= json_encode(\App\Utils\PathHelper::getUploadsPlaceholderUrl()) ?>;
    </script>

    <!-- 追加JavaScript -->
    <?php if (!empty($additionalJs)): ?>
        <?php foreach ($additionalJs as $js): ?>
            <?= \App\Utils\AssetHelper::scriptTag($js) ?>
        <?php endforeach; ?>
    <?php endif; ?>

    <!-- インラインスクリプト -->
    <?php if (!empty($inlineScripts)): ?>
        <?php foreach ($inlineScripts as $script): ?>
            <script nonce="<?= \App\Security\CspMiddleware::getInstance()->getNonce() ?>">
                <?= $script ?>
            </script>
        <?php endforeach; ?>
    <?php endif; ?>
</body>
</html>
