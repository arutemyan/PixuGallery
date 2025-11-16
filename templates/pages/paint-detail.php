<?php
/**
 * ペイント詳細ページ（コンテンツのみ）
 *
 * 必要な変数:
 * - $illust: イラストデータ
 * - $relatedIllusts: 関連イラスト
 */

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

$tags = []; // Paint機能ではまだタグ未実装
?>
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
            <div class="detail-nsfw-badge">NSFW / 18+</div>
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
                <button class="action-btn" id="btnOpenTimelapse" onclick="openTimelapseOverlay(<?= $illust['id'] ?>)">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="svg-vertical-align">
                        <polygon points="5 3 19 12 5 21 5 3"></polygon>
                    </svg>
                    タイムラプスを再生<?php if ($timelapseSize > 0): ?><span class="small-muted timelapse-size-note">(<?= formatFileSize($timelapseSize) ?>)</span><?php endif; ?>
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
                <div class="timelapse-options timelapse-options-center">
                    <label class="timelapse-option-label">
                        <input type="checkbox" id="ignoreTimestamps" onchange="toggleIgnoreTimestamps(this.checked)" checked>
                        <span>時間を無視（等間隔再生）</span>
                    </label>
                    <div>
                        <small class="small-muted">
                            ※ チェックを外すと制作時の実時間で再生します（タイムスタンプが記録されている場合）
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>
