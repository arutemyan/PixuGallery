<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/Security/SecurityUtil.php';

use App\Models\GroupPost;
use App\Models\Theme;
use App\Models\Setting;

// IDパラメータの検証
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: /index.php');
    exit;
}

$groupPostId = (int)$_GET['id'];

try {
    // テーマ設定を取得
    $themeModel = new Theme();
    $theme = $themeModel->getCurrent();

    // サイト設定を取得
    $settingModel = new Setting();
    $showViewCount = $settingModel->get('show_view_count', '1') === '1';

    // 設定を読み込み
    $config = require __DIR__ . '/../config/config.php';
    $nsfwConfig = $config['nsfw'];
    $ageVerificationMinutes = $nsfwConfig['age_verification_minutes'];
    $nsfwConfigVersion = $nsfwConfig['config_version'];

    // グループ投稿を取得
    $groupPostModel = new GroupPost();
    $groupPost = $groupPostModel->getById($groupPostId);

    if ($groupPost === null) {
        header('Location: /index.php');
        exit;
    }

    // 閲覧回数をインクリメント
    $groupPostModel->incrementViewCount($groupPostId);

} catch (Exception $e) {
    error_log('Group Detail Error: ' . $e->getMessage());
    header('Location: /index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= escapeHtml($groupPost['title']) ?> - <?= escapeHtml($theme['site_title'] ?? 'イラストポートフォリオ') ?></title>
    <meta name="description" content="<?= escapeHtml($groupPost['detail'] ?? $groupPost['title']) ?>">

    <?php
    // SNS共有用の画像パス（最初の画像）
    $isSensitive = isset($groupPost['is_sensitive']) && $groupPost['is_sensitive'] == 1;
    $shareImagePath = '';
    if (!empty($groupPost['images']) && !empty($groupPost['images'][0]['thumb_path'])) {
        $shareImagePath = $groupPost['images'][0]['thumb_path'];

        if ($isSensitive) {
            // NSFW画像の場合はNSFWフィルター版を使用
            $pathInfo = pathinfo($shareImagePath);
            $nsfwFilename = basename($pathInfo['filename'] . '_nsfw.' . ($pathInfo['extension'] ?? 'webp'));
            $shareImagePath = $pathInfo['dirname'] . '/' . $nsfwFilename;
        }
    }

    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
    $fullUrl = $protocol . ($_SERVER['HTTP_HOST'] ?? 'localhost') . $_SERVER['REQUEST_URI'];
    $imageUrl = !empty($shareImagePath) ? $protocol . ($_SERVER['HTTP_HOST'] ?? 'localhost') . '/' . $shareImagePath : '';
    ?>

    <!-- OGP -->
    <meta property="og:title" content="<?= escapeHtml($groupPost['title']) ?>">
    <meta property="og:type" content="article">
    <meta property="og:url" content="<?= escapeHtml($fullUrl) ?>">
    <meta property="og:description" content="<?= escapeHtml(mb_substr($groupPost['detail'] ?? $groupPost['title'], 0, 200)) ?>">
    <meta property="og:site_name" content="<?= escapeHtml($theme['site_title'] ?? 'イラストポートフォリオ') ?>">
    <?php if (!empty($imageUrl)): ?>
    <meta property="og:image" content="<?= escapeHtml($imageUrl) ?>">
    <meta property="og:image:alt" content="<?= escapeHtml($groupPost['title']) ?>">
    <?php endif; ?>

    <!-- Twitter Card -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?= escapeHtml($groupPost['title']) ?>">
    <meta name="twitter:description" content="<?= escapeHtml(mb_substr($groupPost['detail'] ?? $groupPost['title'], 0, 200)) ?>">
    <?php if (!empty($imageUrl)): ?>
    <meta name="twitter:image" content="<?= escapeHtml($imageUrl) ?>">
    <?php endif; ?>

    <!-- CSS -->
    <link href="https://fonts.googleapis.com/css2?family=Noto+Serif+JP:wght@400;700&display=swap" rel="stylesheet">
    <link href="/res/css/main.css" rel="stylesheet">

    <!-- テーマカラー -->
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
            --link-hover-color: <?= escapeHtml($theme['link_hover_color'] ?? '#a177ff') ?>;
            --tag-bg-color: <?= escapeHtml($theme['tag_bg_color'] ?? '#8B5AFA') ?>;
            --tag-text-color: <?= escapeHtml($theme['tag_text_color'] ?? '#ffffff') ?>;
            --filter-active-bg-color: <?= escapeHtml($theme['filter_active_bg_color'] ?? '#8B5AFA') ?>;
            --filter-active-text-color: <?= escapeHtml($theme['filter_active_text_color'] ?? '#ffffff') ?>;
        }

        body {
            background-color: var(--background-color);
        }

        header {
            background: linear-gradient(135deg, var(--secondary-color) 0%, var(--primary-color) 100%);
            <?php if (!empty($theme['header_image'])): ?>
            background-image: url('/<?= escapeHtml($theme['header_image']) ?>');
            background-size: cover;
            background-position: center;
            background-blend-mode: overlay;
            <?php endif; ?>
        }

        .btn-primary,
        .btn-secondary {
            background: var(--primary-color);
        }

        .btn-primary:hover,
        .btn-secondary:hover {
            background: var(--secondary-color);
        }
    </style>
</head>
<body data-age-verification-minutes="<?= $ageVerificationMinutes ?>" data-nsfw-config-version="<?= $nsfwConfigVersion ?>" data-group-post-id="<?= $groupPostId ?>" data-is-sensitive="<?= $isSensitive ? '1' : '0' ?>">
    <script>
        const AGE_VERIFICATION_MINUTES = parseInt(document.body.dataset.ageVerificationMinutes) || 10080;
        const NSFW_CONFIG_VERSION = parseInt(document.body.dataset.nsfwConfigVersion) || 1;
    </script>

    <!-- 年齢確認モーダル -->
    <div id="ageVerificationModal" class="modal">
        <div class="modal-dialog">
            <div class="modal-header">
                <h2 class="modal-title">年齢確認</h2>
                <button type="button" class="modal-close" onclick="denyAge()">&times;</button>
            </div>
            <div class="modal-body">
                <p>このコンテンツは18歳未満の閲覧に適さない可能性があります。</p>
                <p><strong>あなたは18歳以上ですか？</strong></p>
                <p style="font-size: 0.9em; color: #999; margin-top: 20px;">
                    <?php
                    if ($ageVerificationMinutes < 60) {
                        $displayTime = $ageVerificationMinutes . '分間';
                    } elseif ($ageVerificationMinutes < 1440) {
                        $displayTime = round($ageVerificationMinutes / 60, 1) . '時間';
                    } else {
                        $displayTime = round($ageVerificationMinutes / 1440, 1) . '日間';
                    }
                    ?>
                    ※一度確認すると、ブラウザに記録され一定期間（<?= $displayTime ?>）は再度確認されません。<br>
                    記録を削除したい場合はブラウザのCookieを削除してください。
                </p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="denyAge()">いいえ</button>
                <button type="button" class="btn btn-primary" onclick="confirmAge()">はい、18歳以上です</button>
            </div>
        </div>
    </div>

    <!-- ヘッダー -->
    <header>
        <div class="header-content">
            <a href="/index.php" class="back-link">一覧に戻る</a>
            <?php if (!empty($theme['logo_image'])): ?>
                <img src="/<?= escapeHtml($theme['logo_image']) ?>" alt="<?= escapeHtml($theme['site_title'] ?? 'ロゴ') ?>" style="max-height: 60px;">
            <?php else: ?>
                <span style="color: white; font-weight: 700;"><?= !empty($theme['header_html']) ? escapeHtml($theme['header_html']) : escapeHtml($theme['site_title'] ?? 'イラストポートフォリオ') ?></span>
            <?php endif; ?>
        </div>
    </header>

    <!-- メインコンテンツ -->
    <div class="container">
        <div class="detail-card">
            <!-- メタデータ -->
            <div class="detail-content">
                <?php if ($isSensitive): ?>
                    <div class="detail-nsfw-badge">NSFW / 18+</div>
                <?php endif; ?>

                <h1 class="detail-title"><?= escapeHtml($groupPost['title']) ?></h1>

                <div class="detail-meta">
                    <span class="meta-item">
                        <i class="bi bi-images me-1"></i><?= $groupPost['image_count'] ?>枚
                    </span>
                    <span class="meta-item">
                        📅 <?= date('Y年m月d日', strtotime($groupPost['created_at'])) ?>
                    </span>
                    <?php if ($showViewCount && isset($groupPost['view_count'])): ?>
                        <span class="meta-item view-count">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16" style="vertical-align: -2px;">
                                <path d="M16 8s-3-5.5-8-5.5S0 8 0 8s3 5.5 8 5.5S16 8 16 8zM1.173 8a13.133 13.133 0 0 1 1.66-2.043C4.12 4.668 5.88 3.5 8 3.5c2.12 0 3.879 1.168 5.168 2.457A13.133 13.133 0 0 1 14.828 8c-.058.087-.122.183-.195.288-.335.48-.83 1.12-1.465 1.755C11.879 11.332 10.119 12.5 8 12.5c-2.12 0-3.879-1.168-5.168-2.457A13.134 13.134 0 0 1 1.172 8z"/>
                                <path d="M8 5.5a2.5 2.5 0 1 0 0 5 2.5 2.5 0 0 0 0-5zM4.5 8a3.5 3.5 0 1 1 7 0 3.5 3.5 0 0 1-7 0z"/>
                            </svg>
                            <?= number_format($groupPost['view_count']) ?> 回閲覧
                        </span>
                    <?php endif; ?>
                </div>

                <?php if (!empty($groupPost['tags'])): ?>
                    <div class="detail-tags">
                        <?php
                        $tags = explode(',', $groupPost['tags']);
                        foreach ($tags as $tag):
                            $tag = trim($tag);
                            if (!empty($tag)):
                        ?>
                            <span class="tag"><?= escapeHtml($tag) ?></span>
                        <?php
                            endif;
                        endforeach;
                        ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($groupPost['detail'])): ?>
                    <div class="detail-description"><?= nl2br(escapeHtml($groupPost['detail'])) ?></div>
                <?php endif; ?>
            </div>

            <!-- グループ内の全画像を表示 -->
            <?php foreach ($groupPost['images'] as $index => $image):
                $imagePath = '/' . escapeHtml($image['image_path']);

                // センシティブ画像の場合、最初はNSFWフィルター版を表示
                if ($isSensitive) {
                    $pathInfo = pathinfo($imagePath);
                    $nsfwPath = $pathInfo['dirname'] . '/' . $pathInfo['filename'] . '_nsfw.' . ($pathInfo['extension'] ?? '');
                    $displayPath = $nsfwPath;
                } else {
                    $displayPath = $imagePath;
                }
            ?>
                <img
                    id="groupImage<?= $index ?>"
                    src="<?= $displayPath ?>"
                    <?= $isSensitive ? 'data-original="' . $imagePath . '"' : '' ?>
                    alt="<?= escapeHtml($groupPost['title']) ?> - <?= ($index + 1) ?>"
                    class="detail-image"
                    style="<?= $index > 0 ? 'margin-top: 20px;' : '' ?>"
                >
            <?php endforeach; ?>
        </div>
    </div>

    <!-- フッター -->
    <footer>
        <p><?= !empty($theme['footer_html']) ? nl2br(escapeHtml($theme['footer_html'])) : '&copy; ' . date('Y') . ' イラストポートフォリオ. All rights reserved.' ?></p>
    </footer>

    <!-- JavaScript -->
    <script src="/res/js/detail.js?v=<?= $nsfwConfigVersion ?>"></script>
    <script>
        // グループ画像のNSFWフィルター解除
        function confirmAge() {
            setAgeVerification();
            hideAgeVerificationModal();

            // 全画像を切り替え
            let index = 0;
            let img;
            while ((img = document.getElementById('groupImage' + index))) {
                if (img.dataset.original) {
                    img.src = img.dataset.original;
                }
                index++;
            }
        }

        // 初期化
        document.addEventListener('DOMContentLoaded', function() {
            const isSensitive = document.body.dataset.isSensitive === '1';

            if (isSensitive && checkAgeVerification()) {
                // 年齢確認済みなら全画像を表示
                let index = 0;
                let img;
                while ((img = document.getElementById('groupImage' + index))) {
                    if (img.dataset.original) {
                        img.src = img.dataset.original;
                    }
                    index++;
                }
            } else if (isSensitive) {
                // 未確認なら年齢確認モーダルを表示
                showAgeVerificationModal();
            }
        });
    </script>
</body>
</html>
