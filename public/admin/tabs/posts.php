<?php
require_once __DIR__ . '/tab_utils.php';
App\Admin\Tabs\checkAccess();
?>
<div class="row">
    <!-- 画像アップロードフォーム -->
    <div class="col-lg-5">
        <!-- クリップボードからアップロード -->
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <div>
                    <i class="bi bi-clipboard-check me-2"></i>クリップボードから投稿
                </div>
                <button type="button" class="btn btn-sm btn-outline-primary" id="toggleClipboardUpload">
                    <i class="bi bi-chevron-down" id="clipboardToggleIcon"></i>
                </button>
            </div>
            <div class="card-body" id="clipboardUploadSection" style="display: none;">
                <div id="clipboardAlert" class="alert alert-success" role="alert" style="display: none;"></div>
                <div id="clipboardError" class="alert alert-danger" role="alert" style="display: none;"></div>

                <div class="alert alert-info mb-3">
                    <i class="bi bi-info-circle me-2"></i>
                    <strong>使い方:</strong> 下のエリアをクリックして <kbd>Ctrl+V</kbd> (Mac: <kbd>⌘+V</kbd>) で画像を貼り付けてください
                </div>

                <form id="clipboardUploadForm">
                    <input type="hidden" name="csrf_token" value="<?= escapeHtml($csrfToken) ?>">

                    <!-- ペーストエリア -->
                    <div class="mb-3">
                        <label class="form-label">画像を貼り付け</label>
                        <div id="clipboardPasteArea" class="clipboard-paste-area" tabindex="0">
                            <div id="clipboardPasteHint" class="text-center text-muted">
                                <i class="bi bi-clipboard2-plus icon-large"></i>
                                <p class="mt-2">クリックしてフォーカスし、Ctrl+V で画像を貼り付け</p>
                            </div>
                            <div id="clipboardPreview" class="clipboard-preview d-none-important">
                                <img id="clipboardPreviewImg" alt="プレビュー" class="clipboard-preview-img">
                                <button type="button" class="btn btn-sm btn-danger absolute-top-right" id="clearClipboardImage">
                                    <i class="bi bi-x-circle"></i> クリア
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="clipboardTitle" class="form-label">タイトル <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="clipboardTitle" name="title" required>
                    </div>

                    <div class="mb-3">
                        <label for="clipboardTags" class="form-label">タグ（カンマ区切り）</label>
                        <input type="text" class="form-control" id="clipboardTags" name="tags" placeholder="例: R18, ファンタジー, ドラゴン">
                    </div>

                    <div class="mb-3">
                        <label for="clipboardDetail" class="form-label">詳細説明</label>
                        <textarea class="form-control" id="clipboardDetail" name="detail" rows="3"></textarea>
                    </div>

                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="clipboardIsSensitive" name="is_sensitive" value="1">
                            <label class="form-check-label" for="clipboardIsSensitive">
                                センシティブコンテンツ（18禁）
                            </label>
                        </div>
                    </div>

                    <div class="mb-3">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="clipboardIsVisible" name="is_visible" value="1" checked>
                            <label class="form-check-label" for="clipboardIsVisible">
                                <strong>公開ページに表示する</strong>
                            </label>
                            <div class="form-text">オフにすると、この投稿は管理画面でのみ表示されます</div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="clipboardFormat" class="form-label">保存形式</label>
                        <select class="form-select" id="clipboardFormat" name="format">
                            <option value="webp" selected>WebP（推奨・軽量）</option>
                            <option value="jpg">JPEG</option>
                            <option value="png">PNG</option>
                        </select>
                        <div class="form-text">WebPは高品質かつファイルサイズが小さいため推奨です</div>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary" id="clipboardUploadBtn" disabled>
                            <i class="bi bi-upload me-2"></i>アップロード
                        </button>
                        <button type="button" class="btn btn-secondary" id="clipboardCancelBtn">
                            <i class="bi bi-x-circle me-2"></i>キャンセル
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <i class="bi bi-cloud-upload me-2"></i>新規投稿
            </div>
            <div class="card-body">
                <div id="uploadAlert" class="alert alert-success d-none" role="alert"></div>
                <div id="uploadError" class="alert alert-danger d-none" role="alert"></div>

                <form id="uploadForm">
                    <input type="hidden" name="csrf_token" value="<?= escapeHtml($csrfToken) ?>">

                    <div class="mb-3">
                        <label for="title" class="form-label">タイトル <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="title" name="title" required>
                    </div>

                    <div class="mb-3">
                        <label for="tags" class="form-label">タグ（カンマ区切り）</label>
                        <input type="text" class="form-control" id="tags" name="tags" placeholder="例: R18, ファンタジー, ドラゴン">
                    </div>

                    <div class="mb-3">
                        <label for="detail" class="form-label">詳細説明</label>
                        <textarea class="form-control" id="detail" name="detail" rows="3"></textarea>
                    </div>

                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="isSensitive" name="is_sensitive" value="1">
                            <label class="form-check-label" for="isSensitive">
                                センシティブコンテンツ（18禁）
                            </label>
                            <div class="form-text">18歳未満の閲覧に適さないコンテンツの場合はチェックしてください</div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="isVisible" name="is_visible" value="1" checked>
                            <label class="form-check-label" for="isVisible">
                                <strong>公開ページに表示する</strong>
                            </label>
                            <div class="form-text">オフにすると、この投稿は管理画面でのみ表示されます</div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="image" class="form-label">画像ファイル <span class="text-danger">*</span></label>
                        <input type="file" class="form-control" id="image" name="image" accept="image/jpeg,image/png,image/webp" required>
                        <div class="form-text">JPEG, PNG, WebP形式（最大10MB）</div>
                        <img id="imagePreview" alt="プレビュー">
                    </div>

                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-upload me-2"></i>アップロード
                    </button>
                </form>
            </div>
        </div>

        <!-- 一括アップロード -->
        <div class="card mt-4">
            <div class="card-header">
                <i class="bi bi-file-earmark-image me-2"></i>一括アップロード
            </div>
            <div class="card-body">
                <div id="bulkUploadAlert" class="alert alert-success d-none" role="alert"></div>
                <div id="bulkUploadError" class="alert alert-danger d-none" role="alert"></div>

                <form id="bulkUploadForm">
                    <input type="hidden" name="csrf_token" value="<?= escapeHtml($csrfToken) ?>">

                    <div class="mb-3">
                        <label for="bulkImages" class="form-label">画像を選択 (複数可)</label>
                        <input type="file" class="form-control" id="bulkImages" name="images[]" accept="image/*" multiple required>
                        <div class="form-text">
                            一括でアップロードした画像は<strong>すべて非表示状態</strong>で登録されます。<br>
                            タイトルやタグは後から編集画面で設定してください。
                        </div>
                    </div>

                    <div class="mb-3">
                        <div id="bulkPreviewList" class="row g-2"></div>
                    </div>

                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-cloud-upload me-2"></i>一括アップロード
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- 投稿一覧 -->
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <div>
                    <i class="bi bi-images me-2"></i>投稿一覧
                </div>
                <div class="d-flex align-items-center gap-2 d-none" id="bulkActionButtons">
                    <button type="button" class="btn btn-sm btn-outline-primary" id="selectAllBtn">
                        <i class="bi bi-check-square me-1"></i>全選択
                    </button>
                    <span class="badge bg-secondary d-none" id="selectionCount">0件選択中</span>
                    <div class="btn-group" role="group">
                        <button type="button" class="btn btn-sm btn-success" id="bulkPublishBtn" disabled>
                            <i class="bi bi-eye me-1"></i>一括公開
                        </button>
                        <button type="button" class="btn btn-sm btn-warning" id="bulkUnpublishBtn" disabled>
                            <i class="bi bi-eye-slash me-1"></i>一括非公開
                        </button>
                    </div>
                </div>
            </div>
            <div class="card-body p-0">
                <div id="postsList">
                    <div class="text-center p-4 text-muted">
                        <div class="spinner-border" role="status">
                            <span class="visually-hidden">読み込み中...</span>
                        </div>
                        <p class="mt-2">投稿を読み込み中...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>