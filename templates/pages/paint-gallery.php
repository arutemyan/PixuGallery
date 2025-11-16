<?php
/**
 * ペイントギャラリーページ（コンテンツのみ）
 */
?>
<div class="container">
    <!-- フィルターセクション -->
    <div class="filter-section">
        <div class="filter-row">
            <span class="filter-label">表示:</span>
            <button class="filter-btn active" data-nsfw-filter="all" onclick="setNSFWFilter('all')">すべて</button>
            <button class="filter-btn" data-nsfw-filter="safe" onclick="setNSFWFilter('safe')">一般</button>
            <button class="filter-btn" data-nsfw-filter="nsfw" onclick="setNSFWFilter('nsfw')">NSFW</button>
        </div>
        <div class="filter-row mt-3">
            <span class="filter-label">タグ:</span>
            <button class="tag-btn active" data-tag="" onclick="showAllPaints()">すべて</button>
            <div id="tagList"></div>
        </div>
        <div class="filter-row mt-3">
            <span class="filter-label">検索:</span>
            <div class="search-box">
                <input
                    type="text"
                    id="searchInput"
                    class="search-input"
                    placeholder="タイトルや説明で検索..."
                >
            </div>
        </div>
    </div>

    <!-- ギャラリーグリッド -->
    <div id="galleryGrid" class="gallery-grid">
        <!-- JavaScriptで動的に読み込まれます -->
    </div>

    <!-- ローディング -->
    <div id="loading" class="loading">
        <div class="spinner"></div>
        <p>読み込み中...</p>
    </div>
</div>
