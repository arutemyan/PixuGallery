/**
 * 管理画面 - グループ投稿タブ JavaScript
 *
 * グローバル変数 (admin-common.jsで定義):
 * - window.ADMIN_PATH
 * - window.CSRF_TOKEN
 * - window.escapeHtml()
 * - window.editModal
 */

// グローバル変数をローカルエイリアスとして取得（コードの可読性向上）
const ADMIN_PATH = window.ADMIN_PATH;
const CSRF_TOKEN = window.CSRF_TOKEN;
const escapeHtml = window.escapeHtml;

$(document).ready(function() {
    // グループ画像プレビュー
    $('#groupImages').on('change', function(e) {
        const files = e.target.files;
        const $previewList = $('#groupPreviewList');
        $previewList.empty();

        if (files.length > 0) {
            Array.from(files).forEach(function(file, index) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    $previewList.append(`
                        <div class="col-4 col-md-3">
                            <div class="position-relative">
                                <img src="${e.target.result}" class="img-thumbnail" data-inline-style="width: 100%; height: 100px; object-fit: cover;">
                                <span class="badge bg-primary position-absolute" data-inline-style="top: 5px; right: 5px;">${index + 1}</span>
                            </div>
                        </div>
                    `);
                };
                reader.readAsDataURL(file);
            });
        }
    });

    // グループ投稿フォーム送信
    $('#groupUploadForm').on('submit', function(e) {
        e.preventDefault();

        const formData = new FormData(this);

        // チェックボックスの値を明示的に設定
        formData.set('is_sensitive', $('#groupPostIsSensitive').is(':checked') ? '1' : '0');
        formData.set('is_visible', $('#groupPostIsVisible').is(':checked') ? '1' : '0');

        const $submitBtn = $(this).find('button[type="submit"]');
        const originalText = $submitBtn.html();

        // ボタンを無効化
        $submitBtn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>アップロード中...');
        $('#groupUploadAlert').addClass('d-none');
        $('#groupUploadError').addClass('d-none');

        // Use ajaxAdmin wrapper to centralize alerts; keep form-specific UI updates here.
        window.ajaxAdmin({
            url: '/' + ADMIN_PATH + '/api/group_upload.php',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            target: '#groupUploadAlert',
            success: function(response) {
                if (response && response.success) {
                    // form reset + preview clear
                    $('#groupUploadForm')[0].reset();
                    $('#groupPreviewList').empty();

                    // reload list
                    loadGroupPosts();

                    // ensure any custom alert element is shown briefly
                    window.showAdminAlert({type: 'success', message: response.message || 'グループ投稿を作成しました', target: '#groupUploadAlert', timeout: window.ADMIN_ALERT_TIMEOUT_INFO});
                } else {
                    const err = response && response.error ? response.error : 'アップロードに失敗しました';
                    window.showAdminAlert({type: 'error', message: err, target: '#groupUploadError', timeout: window.ADMIN_ALERT_TIMEOUT_ERROR});
                }
            },
            error: function(jqXHR) {
                let errorMsg = 'サーバーエラーが発生しました';
                try {
                    if (jqXHR && jqXHR.responseJSON && jqXHR.responseJSON.error) errorMsg = jqXHR.responseJSON.error;
                } catch (e) {}
                window.showAdminAlert({type: 'error', message: errorMsg, target: '#groupUploadError', timeout: window.ADMIN_ALERT_TIMEOUT_ERROR});
            },
            complete: function() {
                $submitBtn.prop('disabled', false).html(originalText);
            }
        });
    });

    // タブが切り替わったときにグループ投稿を読み込み
    $('#group-posts-tab').on('shown.bs.tab', function() {
        loadGroupPosts();
    });
});

// ===== グループ投稿機能 =====

/**
 * グループ投稿一覧を読み込み
 */
function loadGroupPosts() {
    $.ajax({
        url: '/' + ADMIN_PATH + '/api/group_posts.php',
        type: 'GET',
        success: function(response) {
            if (response.success) {
                renderGroupPosts(response.posts);
            } else {
                $('#groupPostsList').html('<div class="alert alert-danger">グループ投稿の読み込みに失敗しました</div>');
            }
        },
        error: function() {
            $('#groupPostsList').html('<div class="alert alert-danger">サーバーエラーが発生しました</div>');
        }
    });
}

/**
 * グループ投稿一覧を描画
 */
function renderGroupPosts(posts) {
    const $list = $('#groupPostsList');
    $list.empty();

    if (posts.length === 0) {
        $list.html('<div class="text-center py-5 text-muted">グループ投稿がありません</div>');
        return;
    }

    posts.forEach(function(post) {
        const visibilityBadge = post.is_visible == 1
            ? '<span class="badge bg-success">公開</span>'
            : '<span class="badge bg-secondary">非公開</span>';
        const nsfwBadge = post.is_sensitive == 1
            ? '<span class="badge bg-danger ms-1">NSFW</span>'
            : '';

        const thumbUrl = post.thumb_path ? '/' + post.thumb_path : '/res/images/no-image.svg';

        $list.append(`
            <div class="border-bottom pb-3 mb-3">
                <div class="row align-items-center">
                        <div class="col-md-2">
                        <img src="${thumbUrl}" class="img-thumbnail" data-inline-style="width: 100%; aspect-ratio: 1; object-fit: cover;">
                    </div>
                    <div class="col-md-6">
                        <h5 class="mb-1">${escapeHtml(post.title)} ${visibilityBadge}${nsfwBadge}</h5>
                        <p class="text-muted mb-1 small">
                            <i class="bi bi-images me-1"></i>${post.image_count}枚
                            <span class="ms-2"><i class="bi bi-calendar me-1"></i>${post.created_at}</span>
                        </p>
                        ${post.tags ? '<p class="mb-0 small"><i class="bi bi-tags me-1"></i>' + escapeHtml(post.tags) + '</p>' : ''}
                    </div>
                    <div class="col-md-4 text-end">
                        <div class="btn-group" role="group">
                            <button class="btn btn-sm btn-outline-primary" onclick="editGroupPost(${post.id})" title="編集">
                                <i class="bi bi-pencil"></i> 編集
                            </button>
                            <button class="btn btn-sm btn-outline-info" onclick="addImagesToGroup(${post.id})" title="画像追加">
                                <i class="bi bi-plus-circle"></i> 画像追加
                            </button>
                            <button class="btn btn-sm btn-outline-success" onclick="shareGroupPostToSNS(${post.id}, '${escapeHtml(post.title).replace(/'/g, "\\'")}', ${post.is_sensitive})" title="SNS共有">
                                <i class="bi bi-share"></i>
                            </button>
                            <button class="btn btn-sm btn-outline-danger" onclick="deleteGroupPost(${post.id}, '${escapeHtml(post.title).replace(/'/g, "\\'")}')">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `);
    });
}

/**
 * グループ投稿を削除
 */
function deleteGroupPost(id, title) {
    if (!confirm(`グループ投稿「${title}」を削除しますか？\n※内の画像も全て削除されます`)) {
        return;
    }

    $.ajax({
        url: '/' + ADMIN_PATH + '/api/group_posts.php',
        type: 'POST',
        data: {
            _method: 'DELETE',
            csrf_token: $('input[name="csrf_token"]').val(),
            id: id
        },
        dataType: 'json',
        success: function(response) {
                if (response.success) {
                    window.showAdminAlert({type: 'success', message: response.message || 'グループ投稿を削除しました'});
                    loadGroupPosts();
                } else {
                    window.showAdminAlert({type: 'error', message: '削除に失敗しました: ' + (response.error || '')});
                }
        },
            error: function(xhr) {
                let msg = 'サーバーエラーが発生しました';
                if (xhr && xhr.responseJSON && xhr.responseJSON.error) msg = xhr.responseJSON.error;
                window.showAdminAlert({type: 'error', message: msg});
            }
    });
}

/**
 * グループ投稿を編集
 */
function editGroupPost(groupPostId) {
    $.ajax({
        url: '/' + ADMIN_PATH + '/api/group_posts.php?id=' + groupPostId,
        type: 'GET',
        dataType: 'json',
        success: function(response) {
            if (response.success && response.data) {
                const post = response.data;

                // 編集モーダルHTMLを生成
                const modalHtml = `
                    <div class="modal fade" id="editGroupModal" tabindex="-1">
                        <div class="modal-dialog modal-lg">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title">
                                        <i class="bi bi-pencil me-2"></i>グループ投稿を編集
                                    </h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body">
                                    <div class="alert alert-success d-none" id="editGroupAlert"></div>
                                    <div class="alert alert-danger d-none" id="editGroupError"></div>

                                    <form id="editGroupForm">
                                        <input type="hidden" id="editGroupPostId" name="id" value="${post.id}">
                                        <input type="hidden" name="csrf_token" value="${$('input[name="csrf_token"]').val()}">

                                        <div class="mb-3">
                                            <label for="editGroupTitle" class="form-label">タイトル <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control" id="editGroupTitle" name="title" value="${escapeHtml(post.title)}" required>
                                        </div>

                                        <div class="mb-3">
                                            <label for="editGroupTags" class="form-label">タグ（カンマ区切り）</label>
                                            <input type="text" class="form-control" id="editGroupTags" name="tags" value="${escapeHtml(post.tags || '')}" placeholder="例: イラスト,風景,オリジナル">
                                        </div>

                                        <div class="mb-3">
                                            <label for="editGroupDetail" class="form-label">詳細説明</label>
                                            <textarea class="form-control" id="editGroupDetail" name="detail" rows="3">${escapeHtml(post.detail || '')}</textarea>
                                        </div>

                                        <div class="mb-3">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" id="editGroupIsSensitive" name="is_sensitive" value="1" ${post.is_sensitive == 1 ? 'checked' : ''}>
                                                <label class="form-check-label" for="editGroupIsSensitive">
                                                    NSFW（センシティブなコンテンツ）
                                                </label>
                                            </div>
                                        </div>

                                        <div class="mb-3">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" id="editGroupIsVisible" name="is_visible" value="1" ${post.is_visible == 1 ? 'checked' : ''}>
                                                <label class="form-check-label" for="editGroupIsVisible">
                                                    公開する
                                                </label>
                                            </div>
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label">グループ内の画像（${post.image_count}枚）</label>
                                            <div class="row g-2" id="editGroupImagesList">
                                                ${post.images.map(img => `
                                                    <div class="col-md-3 col-sm-4 col-6" data-image-id="${img.id}">
                                                        <div class="card">
                                                            <img src="/${img.thumb_path}" class="card-img-top" data-inline-style="aspect-ratio: 1; object-fit: cover;" alt="画像${img.display_order}">
                                                            <div class="card-body p-2">
                                                                <div class="text-center small text-muted mb-2">順序: ${img.display_order}</div>
                                                                <div class="d-grid gap-1">
                                                                    <button type="button" class="btn btn-sm btn-primary" onclick="replaceGroupImage(${img.id}, ${post.id})">
                                                                        <i class="bi bi-arrow-repeat"></i> 差し替え
                                                                    </button>
                                                                    <button type="button" class="btn btn-sm btn-danger" onclick="deleteGroupImage(${img.id}, ${post.id})" ${post.image_count <= 1 ? 'disabled' : ''}>
                                                                        <i class="bi bi-trash"></i> 削除
                                                                    </button>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                `).join('')}
                                            </div>
                                        </div>
                                    </form>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">キャンセル</button>
                                    <button type="button" class="btn btn-primary" id="saveGroupPostBtn">
                                        <i class="bi bi-save me-1"></i>保存
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                `;

                // 既存のモーダルを削除
                $('#editGroupModal').remove();

                // 新しいモーダルを追加
                $('body').append(modalHtml);
                const editGroupModal = new bootstrap.Modal(document.getElementById('editGroupModal'));
                editGroupModal.show();

                // モーダルが閉じられたらDOMから削除
                $('#editGroupModal').on('hidden.bs.modal', function() {
                    $(this).remove();
                    // バックドロップも確実に削除
                    $('.modal-backdrop').remove();
                    $('body').removeClass('modal-open').css('overflow', '').css('padding-right', '');
                });

                // 保存ボタンのイベント
                $('#saveGroupPostBtn').on('click', function() {
                    saveGroupPost();
                });
            } else {
                window.showAdminAlert({type: 'error', message: 'グループ投稿の取得に失敗しました', target: '#groupPostsList'});
            }
        },
        error: function() {
            window.showAdminAlert({type: 'error', message: 'サーバーエラーが発生しました', target: '#groupPostsList'});
        }
    });
}

/**
 * グループ投稿を保存
 */
function saveGroupPost() {
    const formData = $('#editGroupForm').serialize();
    const $saveBtn = $('#saveGroupPostBtn');
    const originalText = $saveBtn.html();

    $saveBtn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>保存中...');
    $('#editGroupAlert').addClass('d-none');
    $('#editGroupError').addClass('d-none');

    window.ajaxAdmin({
        url: '/' + ADMIN_PATH + '/api/group_posts.php',
        type: 'POST',
        data: formData + '&_method=PUT',
        dataType: 'json',
        target: '#editGroupAlert',
        success: function(response) {
            if (response && response.success) {
                // reload list and close modal
                loadGroupPosts();
                setTimeout(function() {
                    $('#editGroupModal').modal('hide');
                }, 1500);
            } else {
                const err = response && response.error ? response.error : '保存に失敗しました';
                window.showAdminAlert({type: 'error', message: err, target: '#editGroupError', timeout: window.ADMIN_ALERT_TIMEOUT_ERROR});
            }
        },
        error: function(jqXHR) {
            let errorMsg = 'サーバーエラーが発生しました';
            try { if (jqXHR && jqXHR.responseJSON && jqXHR.responseJSON.error) errorMsg = jqXHR.responseJSON.error; } catch (e) {}
            window.showAdminAlert({type: 'error', message: errorMsg, target: '#editGroupError', timeout: window.ADMIN_ALERT_TIMEOUT_ERROR});
        },
        complete: function() {
            $saveBtn.prop('disabled', false).html(originalText);
        }
    });
}

/**
 * グループに画像を追加
 */
function addImagesToGroup(groupPostId) {
    // 画像追加モーダルHTMLを生成
    const modalHtml = `
        <div class="modal fade" id="addGroupImagesModal" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i class="bi bi-plus-circle me-2"></i>グループに画像を追加
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-success d-none" id="addGroupImagesAlert"></div>
                        <div class="alert alert-danger d-none" id="addGroupImagesError"></div>

                        <form id="addGroupImagesForm">
                            <input type="hidden" name="group_post_id" value="${groupPostId}">
                            <input type="hidden" name="csrf_token" value="${$('input[name="csrf_token"]').val()}">

                            <div class="mb-3">
                                <label for="addGroupImageFiles" class="form-label">
                                    画像ファイルを選択 <span class="text-danger">*</span>
                                </label>
                                <input type="file" class="form-control" id="addGroupImageFiles" name="images[]" accept="image/*" multiple required>
                                <div class="form-text">複数の画像を一度に選択できます（最大20MB/ファイル）</div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">プレビュー</label>
                                <div class="row g-2" id="addGroupImagesPreviewList"></div>
                            </div>
                        </form>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">キャンセル</button>
                        <button type="button" class="btn btn-primary" id="uploadGroupImagesBtn" disabled>
                            <i class="bi bi-upload me-1"></i>アップロード
                        </button>
                    </div>
                </div>
            </div>
        </div>
    `;

    // 既存のモーダルを削除
    $('#addGroupImagesModal').remove();

    // 新しいモーダルを追加
    $('body').append(modalHtml);
    const addGroupImagesModal = new bootstrap.Modal(document.getElementById('addGroupImagesModal'));
    addGroupImagesModal.show();

    // モーダルが閉じられたらDOMから削除
    $('#addGroupImagesModal').on('hidden.bs.modal', function() {
        $(this).remove();
        // バックドロップも確実に削除
        $('.modal-backdrop').remove();
        $('body').removeClass('modal-open').css('overflow', '').css('padding-right', '');
    });

    // ファイル選択時のプレビュー
    $('#addGroupImageFiles').on('change', function(e) {
        const files = e.target.files;
        const $previewList = $('#addGroupImagesPreviewList');
        $previewList.empty();

        if (files.length > 0) {
            $('#uploadGroupImagesBtn').prop('disabled', false);

            Array.from(files).forEach(function(file, index) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    $previewList.append(`
                        <div class="col-4 col-md-3">
                            <div class="position-relative">
                                <img src="${e.target.result}" class="img-thumbnail" data-inline-style="width: 100%; height: 100px; object-fit: cover;">
                                <span class="badge bg-primary position-absolute" data-inline-style="top: 5px; right: 5px;">${index + 1}</span>
                            </div>
                        </div>
                    `);
                };
                reader.readAsDataURL(file);
            });
        } else {
            $('#uploadGroupImagesBtn').prop('disabled', true);
        }
    });

    // アップロードボタンのイベント
    $('#uploadGroupImagesBtn').on('click', function() {
        uploadGroupImages();
    });
}

/**
 * グループに画像をアップロード
 */
function uploadGroupImages() {
    const formData = new FormData($('#addGroupImagesForm')[0]);
    const $uploadBtn = $('#uploadGroupImagesBtn');
    const originalText = $uploadBtn.html();

    $uploadBtn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>アップロード中...');
    $('#addGroupImagesAlert').addClass('d-none');
    $('#addGroupImagesError').addClass('d-none');

        window.ajaxAdmin({
            url: '/' + ADMIN_PATH + '/api/group_upload.php',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            target: '#addGroupImagesAlert',
            success: function(response) {
                if (response && response.success) {
                    loadGroupPosts();
                    setTimeout(function() {
                        $('#addGroupImagesModal').modal('hide');
                    }, 1500);
                    window.showAdminAlert({type: 'success', message: response.message || '画像を追加しました', target: '#addGroupImagesAlert', timeout: window.ADMIN_ALERT_TIMEOUT_SUCCESS});
                } else {
                    window.showAdminAlert({type: 'error', message: response && response.error ? response.error : 'アップロードに失敗しました', target: '#addGroupImagesError', timeout: window.ADMIN_ALERT_TIMEOUT_ERROR});
                }
            },
            error: function(jqXHR) {
                let errorMsg = 'サーバーエラーが発生しました';
                try { if (jqXHR && jqXHR.responseJSON && jqXHR.responseJSON.error) errorMsg = jqXHR.responseJSON.error; } catch (e) {}
                window.showAdminAlert({type: 'error', message: errorMsg, target: '#addGroupImagesError', timeout: window.ADMIN_ALERT_TIMEOUT_ERROR});
            },
            complete: function() {
                $uploadBtn.prop('disabled', false).html(originalText);
            }
        });
}

/**
 * グループ投稿をSNSで共有
 */
function shareGroupPostToSNS(groupPostId, title, isSensitive) {
    // 詳細ページのURLを構築
    const protocol = window.location.protocol;
    const host = window.location.host;
    const detailUrl = `${protocol}//${host}/group_detail.php?id=${groupPostId}`;

    // エンコードされたURL
    const encodedUrl = encodeURIComponent(detailUrl);
    const encodedTitle = encodeURIComponent(title);
    const hashtags = 'イラスト,artwork';
    const encodedHashtags = encodeURIComponent(hashtags);

    // センシティブな場合はハッシュタグに追加
    const nsfwHashtag = isSensitive ? ',NSFW' : '';
    const fullHashtags = encodeURIComponent(hashtags + nsfwHashtag);

    // 各SNSの共有URL
    const shareUrls = {
        twitter: `https://twitter.com/intent/tweet?url=${encodedUrl}&text=${encodedTitle}&hashtags=${fullHashtags}`,
        misskey: `https://misskey.io/share?text=${encodedTitle}%0A${encodedUrl}`
    };

    // モーダルHTML
    const modalHtml = `
        <div class="modal fade" id="shareGroupModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i class="bi bi-share me-2"></i>SNSで共有（グループ投稿）
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p class="mb-3"><strong>${escapeHtml(title)}</strong></p>
                        <p class="text-muted small mb-3">
                            共有URL: <a href="${detailUrl}" target="_blank">${detailUrl}</a>
                        </p>
                        ${isSensitive ? '<div class="alert alert-warning"><i class="bi bi-exclamation-triangle me-2"></i>この投稿はNSFWです。</div>' : ''}

                        <div class="d-grid gap-2">
                            <a href="${shareUrls.twitter}" target="_blank" class="btn btn-primary" data-inline-style="background-color: #1DA1F2; border-color: #1DA1F2;">
                                <i class="bi bi-twitter me-2"></i>X (Twitter) で共有
                            </a>
                            <a href="${shareUrls.misskey}" target="_blank" class="btn btn-primary" data-inline-style="background-color: #86b300; border-color: #86b300;">
                                <i class="bi bi-mastodon me-2"></i>Misskey で共有
                            </a>
                        </div>

                        <div class="mt-3">
                            <label class="form-label">共有URL（コピー用）</label>
                            <div class="input-group">
                                <input type="text" class="form-control" id="shareGroupUrlInput" value="${detailUrl}" readonly>
                                <button class="btn btn-outline-secondary" type="button" onclick="copyShareGroupUrl()">
                                    <i class="bi bi-clipboard"></i> コピー
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">閉じる</button>
                    </div>
                </div>
            </div>
        </div>
    `;

    // 既存のモーダルを削除
    $('#shareGroupModal').remove();

    // 新しいモーダルを追加して表示
    $('body').append(modalHtml);
    const shareGroupModal = new bootstrap.Modal(document.getElementById('shareGroupModal'));
    shareGroupModal.show();

    // モーダルが閉じられたらDOMから削除
    $('#shareGroupModal').on('hidden.bs.modal', function() {
        $(this).remove();
        // バックドロップも確実に削除
        $('.modal-backdrop').remove();
        $('body').removeClass('modal-open').css('overflow', '').css('padding-right', '');
    });
}

/**
 * グループ投稿の共有URLをクリップボードにコピー
 */
function copyShareGroupUrl() {
    const input = document.getElementById('shareGroupUrlInput');
    input.select();
    document.execCommand('copy');

    // コピー成功のフィードバック
    const btn = event.target.closest('button');
    const originalHtml = btn.innerHTML;
    btn.innerHTML = '<i class="bi bi-check"></i> コピーしました';
    btn.classList.add('btn-success');
    btn.classList.remove('btn-outline-secondary');

    setTimeout(function() {
        btn.innerHTML = originalHtml;
        btn.classList.remove('btn-success');
        btn.classList.add('btn-outline-secondary');
    }, 2000);
}

/**
 * グループ画像を差し替え
 */
function replaceGroupImage(imageId, groupPostId) {
    // ファイル選択ダイアログを作成
    const input = document.createElement('input');
    input.type = 'file';
    input.accept = 'image/*';

    input.onchange = function(e) {
        const file = e.target.files[0];
        if (!file) return;

        // 確認ダイアログ
        if (!confirm('この画像を差し替えますか？')) {
            return;
        }

        const formData = new FormData();
        formData.append('image', file);
        formData.append('image_id', imageId);
        formData.append('csrf_token', $('input[name="csrf_token"]').val());

        // 画像要素を取得してローディング表示
        const $imageCard = $(`[data-image-id="${imageId}"]`);
        const $img = $imageCard.find('img');
        const originalSrc = $img.attr('src');

        // ボタンを無効化
        $imageCard.find('button').prop('disabled', true);

        $.ajax({
            url: '/' + ADMIN_PATH + '/api/group_image_replace.php',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    // 画像を更新（キャッシュバスター付き）
                    const timestamp = new Date().getTime();
                    $img.attr('src', '/' + response.thumb_path + '?' + timestamp);

                    // 成功メッセージ
                    window.showAdminAlert({type: 'success', message: response.message || '画像を差し替えました', target: '#editGroupAlert', timeout: window.ADMIN_ALERT_TIMEOUT_SUCCESS});

                    // グループ投稿一覧を再読み込み（バックグラウンドで）
                    loadGroupPosts();
                } else {
                    const errorMsg = response.error || '不明なエラー';
                    const debugInfo = response.debug ? '\n\nデバッグ情報:\n' + response.debug : '';
                    window.showAdminAlert({type: 'error', message: '差し替えに失敗しました: ' + errorMsg + debugInfo});
                    console.error('Replace error:', response);
                    $img.attr('src', originalSrc);
                }
            },
            error: function(xhr, status, error) {
                console.error('XHR Error:', xhr);
                console.error('Status:', status);
                console.error('Error:', error);
                console.error('Response Text:', xhr.responseText);

                let errorMsg = 'サーバーエラーが発生しました';
                if (xhr.responseJSON && xhr.responseJSON.error) {
                    errorMsg = xhr.responseJSON.error;
                } else if (xhr.responseText) {
                    errorMsg = xhr.responseText.substring(0, 200);
                }
                window.showAdminAlert({type: 'error', message: '差し替えに失敗しました: ' + errorMsg});
                $img.attr('src', originalSrc);
            },
            complete: function() {
                // ボタンを有効化
                $imageCard.find('button').prop('disabled', false);
            }
        });
    };

    // ファイル選択ダイアログを開く
    input.click();
}

/**
 * グループ画像を削除
 */
function deleteGroupImage(imageId, groupPostId) {
    if (!confirm('この画像を削除しますか？\nこの操作は取り消せません。')) {
        return;
    }

    const $imageCard = $(`[data-image-id="${imageId}"]`);

    // ボタンを無効化
    $imageCard.find('button').prop('disabled', true);

    $.ajax({
        url: '/' + ADMIN_PATH + '/api/group_image_replace.php',
        type: 'POST',
        data: {
            _method: 'DELETE',
            image_id: imageId,
            csrf_token: $('input[name="csrf_token"]').val()
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                // 画像カードをフェードアウトして削除
                $imageCard.fadeOut(300, function() {
                    $(this).remove();

                    // 残りの画像数を更新
                    const remainingImages = $('#editGroupImagesList [data-image-id]').length;
                    const $label = $('#editGroupImagesList').prev('label');
                    $label.text('グループ内の画像（' + remainingImages + '枚）');

                    // 残りが1枚になった場合、すべての削除ボタンを無効化
                    if (remainingImages <= 1) {
                        $('#editGroupImagesList').find('.btn-danger').prop('disabled', true);
                    }
                });

                // 成功メッセージ
                window.showAdminAlert({type: 'success', message: response.message || '画像を削除しました', target: '#editGroupAlert', timeout: window.ADMIN_ALERT_TIMEOUT_SUCCESS});

                // グループ投稿一覧を再読み込み（バックグラウンドで）
                loadGroupPosts();
                } else {
                    const errorMsg = response.error || '不明なエラー';
                    const debugInfo = response.debug ? '\n\nデバッグ情報:\n' + response.debug : '';
                    window.showAdminAlert({type: 'error', message: '削除に失敗しました: ' + errorMsg + debugInfo});
                    console.error('Delete error:', response);
                    $imageCard.find('button').prop('disabled', false);
                }
        },
            error: function(xhr) {
                let errorMsg = 'サーバーエラーが発生しました';
                if (xhr.responseJSON && xhr.responseJSON.error) {
                    errorMsg = xhr.responseJSON.error;
                }
                window.showAdminAlert({type: 'error', message: '削除に失敗しました: ' + errorMsg});
                $imageCard.find('button').prop('disabled', false);
            }
    });
}
