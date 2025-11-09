<?php
/**
 * Paint Detail - イラスト詳細ページ
 * public/paint/detail.php
 */

require_once(__DIR__ . '/../../vendor/autoload.php');
$config = \App\Config\ConfigManager::getInstance()->getConfig();

use App\Models\Theme;
use App\Models\Setting;
use App\Utils\Logger;

/**
 * ファイルサイズをわかりやすい形式にフォーマット
 */
function formatFileSize($bytes) {
    if ($bytes == 0) return '0 B';
    $units = ['B', 'KB', 'MB', 'GB'];
    $i = 0;
    while ($bytes >= 1024 && $i < count($units) - 1) {
        $bytes /= 1024;
        $i++;
    }
    return round($bytes, 1) . ' ' . $units[$i];
}

// テーマ設定を読み込む
try {
    $themeModel = new Theme();
    $theme = $themeModel->getCurrent();
    $siteTitle = $theme['site_title'] ?? 'ペイントギャラリー';
    $siteSubtitle = $theme['site_subtitle'] ?? 'キャンバスで描いたオリジナルイラスト作品集';
} catch (Exception $e) {
    $theme = [];
    $siteTitle = 'ペイントギャラリー';
    $siteSubtitle = 'キャンバスで描いたオリジナルイラスト作品集';
}

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
    
    // タグを配列に変換
    $tags = []; // $illust['tags'] ? explode(',', $illust['tags']) : [];
    
    // 関連イラスト取得（最新のイラストを取得）
    $relatedIllusts = [];
    /*
    if (!empty($tags)) {
        $placeholders = implode(',', array_fill(0, count($tags), '?'));
        $relatedSql = "SELECT DISTINCT
                        i.id,
                        i.title,
                        i.thumb_path,
                        i.image_path
                    FROM paint i
                    INNER JOIN illust_tags it ON i.id = it.paint_id
                    INNER JOIN tags t ON it.tag_id = t.id
                    WHERE t.name IN ($placeholders)
                      AND i.id != ?
                    ORDER BY i.created_at DESC
                    LIMIT 6";
        
        $relatedStmt = $db->prepare($relatedSql);
        foreach ($tags as $index => $tag) {
            $relatedStmt->bindValue($index + 1, trim($tag));
        }
        $relatedStmt->bindValue(count($tags) + 1, $id, PDO::PARAM_INT);
        $relatedStmt->execute();
        $relatedIllusts = $relatedStmt->fetchAll(PDO::FETCH_ASSOC);
    }
    */
    
    // タグがない場合は最新のイラストを取得
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

// NSFW設定を取得
$nsfwConfig = $config['nsfw'];
$ageVerificationMinutes = $nsfwConfig['age_verification_minutes'];
$nsfwConfigVersion = $nsfwConfig['config_version'];

// OGP用の画像URL
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$imageUrl = $protocol . $host . ($illust['thumb_path'] ?? $illust['image_path']);
$pageUrl = $protocol . $host . $_SERVER['REQUEST_URI'];
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= escapeHtml($illust['title']) ?> - <?= escapeHtml($siteTitle) ?></title>
    <meta name="description" content="<?= escapeHtml(mb_substr($illust['detail'] ?? $illust['title'], 0, 200)) ?>">
    
    <!-- OGP -->
    <meta property="og:title" content="<?= escapeHtml($illust['title']) ?>">
    <meta property="og:type" content="article">
    <meta property="og:url" content="<?= escapeHtml($pageUrl) ?>">
    <meta property="og:image" content="<?= escapeHtml($imageUrl) ?>">
    <meta property="og:description" content="<?= escapeHtml(mb_substr($illust['detail'] ?? $illust['title'], 0, 200)) ?>">
    
    <!-- Twitter Card -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?= escapeHtml($illust['title']) ?>">
    <meta name="twitter:description" content="<?= escapeHtml(mb_substr($illust['detail'] ?? $illust['title'], 0, 200)) ?>">
    <meta name="twitter:image" content="<?= escapeHtml($imageUrl) ?>">
    
    <!-- Googleフォント -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Serif+JP:wght@400;600;700&display=swap" rel="stylesheet">
    
    <!-- スタイルシート -->
    <?php echo \App\Utils\AssetHelper::linkTag('/res/css/main.css'); ?>
    <?php echo \App\Utils\AssetHelper::linkTag('/paint/css/gallery.css'); ?>
    <?php echo \App\Utils\AssetHelper::linkTag('/paint/css/detail.css'); ?>

    <!-- テーマカラー -->
    <style>
        <?php require_once(__DIR__ . '/../block/style.php') ?>
    </style>
</head>
<body data-age-verification-minutes="<?= $ageVerificationMinutes ?>" data-nsfw-config-version="<?= $nsfwConfigVersion ?>" data-is-nsfw="<?= !empty($illust['nsfw']) ? '1' : '0' ?>">
    <script>
        // 設定値をdata属性から読み込み（const定義で改ざん防止）
        const AGE_VERIFICATION_MINUTES = parseFloat(document.body.dataset.ageVerificationMinutes) || 10080;
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
        <?php if (!empty($theme['logo_image'])): ?>
            <img src="/<?= escapeHtml($theme['logo_image']) ?>" alt="<?= escapeHtml($theme['site_title'] ?? 'ロゴ') ?>" style="max-height: 80px; margin-bottom: 10px;">
        <?php endif; ?>
        <h1>🎨 <?= escapeHtml($siteTitle) ?></h1>
    </header>
    <a href="/paint/" class="back-link">
        <div class="header-back-button">
            ペイントギャラリーに戻る
        </div>
    </a>
    
    <!-- メインコンテンツ -->
    <div class="detail-container">
        <div class="detail-card">
            <!-- イラスト画像 -->
                <div id="detailImageContainer" class="detail-image-wrapper">
                    <img 
                        id="detailImage"
                        src="<?= escapeHtml($illust['image_path']) ?>" 
                        alt="<?= escapeHtml($illust['title']) ?>"
                        class="detail-image"
                    >
                </div>
            
            <!-- イラスト情報 -->
            <div class="detail-content">
                <div class="detail-header">
                    <h2 class="detail-title"><?= escapeHtml($illust['title']) ?></h2>
                    <div class="detail-meta">
                        <span class="detail-meta-item">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                                <line x1="16" y1="2" x2="16" y2="6"></line>
                                <line x1="8" y1="2" x2="8" y2="6"></line>
                                <line x1="3" y1="10" x2="21" y2="10"></line>
                            </svg>
                            <?= date('Y年m月d日', strtotime($illust['created_at'])) ?>
                        </span>
                        <?php if ($illust['width'] && $illust['height']): ?>
                        <span class="detail-meta-item">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                            </svg>
                            <?= $illust['width'] ?>×<?= $illust['height'] ?>px
                        </span>
                        <?php endif; ?>
                    </div>
                </div>
                
                <?php
                // NSFW handling: if the illust is marked nsfw, show age verification
                $isNsfw = !empty($illust['nsfw']);
                ?>

                <?php if ($isNsfw): ?>
                <div class="detail-nsfw-badge" style="display: inline-block; padding: 6px 12px; background: #ff6b6b; color: white; border-radius: 4px; font-size: 0.9em; margin-bottom: 10px;">NSFW / 18+</div>
                <?php endif; ?>

                <?php if (!empty($illust['detail'])): ?>
                <div class="detail-description">
                    <?= nl2br(escapeHtml($illust['detail'])) ?>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($tags)): ?>
                <div class="detail-tags">
                    <?php foreach ($tags as $tag): ?>
                    <a href="/paint/?tag=<?= urlencode(trim($tag)) ?>" class="tag">
                        <?= escapeHtml(trim($tag)) ?>
                    </a>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
                
                <div class="detail-actions">
                    <?php if (!empty($illust['timelapse_path'])): ?>
                    <?php
                    // Calculate timelapse file size
                    $timelapseSize = 0;
                    if (!empty($illust['timelapse_size'])) {
                        $timelapseSize = $illust['timelapse_size'];
                    } elseif (!empty($illust['timelapse_path'])) {
                        // Fallback: get size from file if not in DB
                        $filePath = $_SERVER['DOCUMENT_ROOT'] . $illust['timelapse_path'];
                        if (file_exists($filePath)) {
                            $timelapseSize = filesize($filePath);
                        }
                    }
                    ?>
                    <button class="action-btn" id="btnOpenTimelapse" onclick="openTimelapseOverlay(<?= $id ?>)">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polygon points="5 3 19 12 5 21 5 3"></polygon>
                        </svg>
                        タイムラプスを再生<?php if ($timelapseSize > 0): ?><span style="font-size: 0.85em; opacity: 0.8; margin-left: 5px;">(<?= formatFileSize($timelapseSize) ?>)</span><?php endif; ?>
                    </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- 関連イラスト -->
        <?php if (!empty($relatedIllusts)): ?>
        <div class="related-section">
            <h3 class="related-title">関連イラスト</h3>
            <div class="related-grid">
                <?php foreach ($relatedIllusts as $related): ?>
                <div class="illust-card" onclick="window.location.href='/paint/detail.php?id=<?= $related['id'] ?>'">
                    <div class="illust-image-wrapper">
                        <img 
                            src="<?= escapeHtml($related['thumb_path'] ?? $related['image_path']) ?>" 
                            alt="<?= escapeHtml($related['title']) ?>"
                            class="illust-image"
                            loading="lazy"
                        >
                    </div>
                    <div class="illust-info">
                        <h4 class="illust-title"><?= escapeHtml($related['title']) ?></h4>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- タイムラプスオーバーレイ -->
    <?php if (!empty($illust['timelapse_path'])): ?>
    <div id="timelapseOverlay" class="timelapse-overlay" onclick="closeTimelapseOverlay(event)">
        <div class="timelapse-overlay-content" onclick="event.stopPropagation()">
            <button class="timelapse-overlay-close" onclick="closeTimelapseOverlay()">&times;</button>
            <h3 class="timelapse-overlay-title">制作過程タイムラプス</h3>
            <div class="timelapse-player">
                <canvas id="timelapseCanvas" class="timelapse-canvas"></canvas>
                <div class="timelapse-controls">
                    <button id="timelapsePlayBtn" class="timelapse-play-btn" onclick="togglePlayback()">▶</button>
                    <div id="timelapseProgress" class="timelapse-progress">
                        <div id="timelapseProgressBar" class="timelapse-progress-bar"></div>
                    </div>
                    <div id="timelapseTime" class="timelapse-time">0 / 0</div>
                    <div class="timelapse-speed">
                        <button class="speed-btn" data-speed="0.5" onclick="changeSpeed(0.5)">0.5x</button>
                        <button class="speed-btn active" data-speed="1" onclick="changeSpeed(1)">1x</button>
                        <button class="speed-btn" data-speed="2" onclick="changeSpeed(2)">2x</button>
                        <button class="speed-btn" data-speed="4" onclick="changeSpeed(4)">4x</button>
                    </div>
                    <div class="timelapse-options" style="margin-top: 10px; text-align: center;">
                        <label style="display: inline-flex; align-items: center; gap: 8px; cursor: pointer;">
                            <input type="checkbox" id="ignoreTimestamps" onchange="toggleIgnoreTimestamps(this.checked)" checked>
                            <span>時間を無視（等間隔再生）</span>
                        </label>
                        <div>
                            <small style="display: block; margin-top: 4px; color: #666; font-size: 0.85em;">
                                ※ チェックを外すと制作時の実時間で再生します（タイムスタンプが記録されている場合）
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- JavaScript -->
    <?php echo \App\Utils\AssetHelper::scriptTag('/paint/js/detail.js'); ?>
    <script>
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
    </script>
</body>
</html>
