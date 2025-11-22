<?php
/**
 * ギャラリーページ（コンテンツのみ）
 *
 * 必要な変数:
 * - $posts: 投稿一覧
 * - $tags: タグ一覧
 * - $paintEnabled: ペイント機能有効フラグ
 */
?>
<div class="container">
    <!-- ペイントギャラリーへのリンク -->
    <?php if (!empty($paintEnabled)): ?>
    <div class="centered-margin">
        <a href="/paint/" class="paint-gallery-btn">
            ペイントギャラリーを見る
        </a>
    </div>
    <?php endif; ?>

    <!-- フィルタエリア -->
    <div class="filter-section">
        <div class="filter-compact">
            <div class="filter-group">
                <span class="filter-label">表示:</span>
                <button class="filter-btn filter-btn-compact active" data-filter="all" onclick="setNSFWFilter('all')">すべて</button>
                <button class="filter-btn filter-btn-compact" data-filter="safe" onclick="setNSFWFilter('safe')">一般</button>
                <button class="filter-btn filter-btn-compact" data-filter="nsfw" onclick="setNSFWFilter('nsfw')">NSFW</button>
                <span class="filter-separator">|</span>
                <button class="toggle-btn active" id="toggleTags" onclick="toggleTagsVisibility()" title="タグの表示/非表示を切り替え">タグ</button>
                <button class="toggle-btn active" id="toggleTitles" onclick="toggleTitlesVisibility()" title="タイトルの表示/非表示を切り替え">表題</button>
            </div>
            <div class="filter-group">
                <span class="filter-label">タグ:</span>
                <button class="tag-btn tag-btn-compact tag-btn-all active" data-tag="" onclick="clearTagFilter(); setActiveTagButton(this);">すべて</button>
                <div id="tagList" class="inline-display">
                    <!-- JavaScriptで動的に読み込まれます -->
                </div>
            </div>
        </div>
    </div>

    <?php if (empty($posts)): ?>
        <div class="empty-state">
            <span class="emoji-large">🎨</span>
            <h2>まだ投稿がありません</h2>
            <p>管理画面から作品を投稿してください</p>
        </div>
    <?php else: ?>
        <div class="grid">
            <?php foreach ($posts as $post): ?>
                <?php
                $isSensitive = isset($post['is_sensitive']) && $post['is_sensitive'] == 1;
                $thumbPath = '/' . escapeHtml($post['thumb_path'] ?? $post['image_path'] ?? '');
                // センシティブ画像の場合、NSFWフィルター版を使用
                if ($isSensitive) {
                    $pathInfo = pathinfo($thumbPath);
                    $nsfwPath = $pathInfo['dirname'] . '/' . $pathInfo['filename'] . '_nsfw.' . ($pathInfo['extension'] ?? 'webp');
                    $imagePath = $nsfwPath;
                } else {
                    $imagePath = $thumbPath;
                }
                $isGroup = isset($post['post_type']) && $post['post_type'] === 'group';
                $viewType = ($isGroup ? 1 : 0);
                $detailUrl = '/detail.php?id=' . $post['id'] . "&viewtype=" . $viewType;
                ?>
                <div class="card <?= $isSensitive ? 'nsfw-card' : '' ?><?= $isGroup ? ' group-card' : '' ?>" data-post-id="<?= $post['id'] ?>" data-post-type="<?= $isGroup ? 'group' : 'single' ?>" data-view-type="<?= $viewType ?>">
                        <div class="card-img-wrapper <?= $isSensitive ? 'nsfw-wrapper' : '' ?> cursor-pointer"
                             <?= $isGroup ? 'onclick="window.location.href=\'' . $detailUrl . '\'"' : 'onclick="openImageOverlay(' . $post['id'] . ', ' . ($isSensitive ? 'true' : 'false') . ', '.$viewType.')"' ?>
                            >
                        <img
                            src="<?= $imagePath ?>"
                            alt="<?= escapeHtml($post['title']) ?>"
                            class="card-image"
                            loading="lazy"
                            onerror="if(!this.dataset.errorHandled){this.dataset.errorHandled='1';this.src='<?= \App\Utils\PathHelper::getUploadsPlaceholderUrl() ?>';}"
                            <?= !$isGroup ? 'data-full-image="/' . escapeHtml($post['image_path'] ?? $post['thumb_path'] ?? '') . '"' : '' ?>
                            data-is-sensitive="<?= $isSensitive ? '1' : '0' ?>"
                        >
                        <?php if ($isGroup && isset($post['image_count'])): ?>
                            <div class="group-badge">
                                <?= $post['image_count'] ?>枚
                            </div>
                        <?php endif; ?>
                        <?php if ($isSensitive): ?>
                            <div class="nsfw-overlay">
                                <div class="nsfw-text">センシティブな内容</div>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($post['tags'])): ?>
                            <div class="card-tags">
                                <?php
                                $postTags = explode(',', $post['tags']);
                                foreach ($postTags as $tag):
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
                    </div>
                    <div class="card-content">
                        <h2 class="card-title"><?= escapeHtml($post['title']) ?></h2>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- ローディングインジケーター -->
        <div id="loadingIndicator" class="loading-indicator">
            <div class="loading-spinner"></div>
            <p>読み込み中...</p>
        </div>
    <?php endif; ?>
</div>

<!-- 画像オーバーレイモーダル -->
<div id="imageOverlay" class="image-overlay" onclick="closeImageOverlay(event)">
    <div class="image-overlay-content">
        <button class="image-overlay-close" onclick="closeImageOverlay(event)">&times;</button>
        <button class="image-overlay-nav image-overlay-prev" onclick="navigateOverlay(event, -1)" aria-label="前の画像">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <polyline points="15 18 9 12 15 6"></polyline>
            </svg>
        </button>
        <button class="image-overlay-nav image-overlay-next" onclick="navigateOverlay(event, 1)" aria-label="次の画像">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <polyline points="9 18 15 12 9 6"></polyline>
            </svg>
        </button>
        <img id="overlayImage" src="" alt="画像プレビュー">
        <a id="overlayDetailButton" href="#" class="btn btn-detail overlay-detail-btn">
            詳細を表示
        </a>
    </div>
</div>

<!-- NSFW警告モーダル（オーバーレイナビゲーション用） -->
<div id="nsfwWarningModal" class="modal">
    <div class="modal-content">
        <h2>⚠️ センシティブなコンテンツ</h2>
        <p>この画像にはセンシティブな内容が含まれています。</p>
        <p>表示しますか？</p>
        <div class="modal-buttons">
            <button class="btn btn-primary" onclick="acceptNsfwWarning()">表示する</button>
            <button class="btn btn-secondary" onclick="cancelNsfwWarning()">キャンセル</button>
        </div>
    </div>
</div>
