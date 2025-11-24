<?php
require_once __DIR__ . '/tab_utils.php';
App\Admin\Tabs\checkAccess();
?>
<div class="row">
    <!-- グループアップロードフォーム -->
    <div class="col-lg-5">
        <div class="card">
            <div class="card-header">
                <i class="bi bi-images me-2"></i>グループ投稿を作成
            </div>
            <div class="card-body">
                <div id="groupUploadAlert" class="alert alert-success d-none" role="alert"></div>
                <div id="groupUploadError" class="alert alert-danger d-none" role="alert"></div>

                <div class="alert alert-info mb-3">
                    <i class="bi bi-info-circle me-2"></i>
                    <strong>グループ投稿とは？</strong><br>
                    複数の画像を1つの投稿として管理します。漫画や連作イラストに最適です。
                </div>

                <form id="groupUploadForm">
                    <input type="hidden" name="csrf_token" value="<?= escapeHtml($csrfToken) ?>">

                    <div class="mb-3">
                        <label for="groupImages" class="form-label">画像を選択 (複数枚) <span class="text-danger">*</span></label>
                        <input type="file" class="form-control" id="groupImages" name="images[]" accept="image/*" multiple required>
                        <div class="form-text">選択した順番で画像が表示されます</div>
                    </div>

                    <div class="mb-3">
                        <div id="groupPreviewList" class="row g-2"></div>
                    </div>

                    <div class="mb-3">
                        <label for="groupPostTitle" class="form-label">タイトル <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="groupPostTitle" name="title" required placeholder="例: 漫画タイトル 第1話">
                    </div>

                    <div class="mb-3">
                        <label for="groupPostTags" class="form-label">タグ（カンマ区切り）</label>
                        <input type="text" class="form-control" id="groupPostTags" name="tags" placeholder="例: 漫画, オリジナル">
                    </div>

                    <div class="mb-3">
                        <label for="groupPostDetail" class="form-label">詳細説明</label>
                        <textarea class="form-control" id="groupPostDetail" name="detail" rows="3"></textarea>
                    </div>

                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="groupPostIsSensitive" name="is_sensitive" value="1">
                            <label class="form-check-label" for="groupPostIsSensitive">
                                センシティブコンテンツ（18禁）
                            </label>
                        </div>
                    </div>

                    <div class="mb-3">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="groupPostIsVisible" name="is_visible" value="1" checked>
                            <label class="form-check-label" for="groupPostIsVisible">
                                <strong>公開ページに表示する</strong>
                            </label>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-cloud-upload me-2"></i>グループ投稿を作成
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- グループ投稿一覧 -->
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <div>
                    <i class="bi bi-folder me-2"></i>グループ投稿一覧
                </div>
                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="loadGroupPosts()">
                    <i class="bi bi-arrow-clockwise me-1"></i>再読み込み
                </button>
            </div>
            <div class="card-body">
                <div id="groupPostsList">
                    <div class="text-center py-5">
                        <div class="spinner-border" role="status">
                            <span class="visually-hidden">読み込み中...</span>
                        </div>
                        <p class="mt-2">グループ投稿を読み込み中...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>