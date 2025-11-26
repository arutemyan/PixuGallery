/**
 * 管理画面 - 投稿（シングル）タブ JavaScript
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
// editModalはDOMロード後に初期化されるため、window.editModal経由でアクセスする

// クリップボードから貼り付けた画像を保持
let clipboardImageFile = null;

// グローバル変数（ページネーション用）
let postsOffset = 0;
let postsLimit = 30;
let allPosts = [];
let totalPostsCount = 0;
let currentEditingIndex = -1; // 現在編集中の投稿のインデックス

$(document).ready(function() {
    // 投稿一覧を読み込み
    loadPosts();

    // クリップボードアップロードのトグル
    $('#toggleClipboardUpload').on('click', function() {
        const $section = $('#clipboardUploadSection');
        const $icon = $('#clipboardToggleIcon');

        if ($section.is(':visible')) {
            $section.slideUp(300);
            $icon.removeClass('bi-chevron-up').addClass('bi-chevron-down');
        } else {
            $section.slideDown(300);
            $icon.removeClass('bi-chevron-down').addClass('bi-chevron-up');
            // フォーカスを当てる
            setTimeout(function() {
                $('#clipboardPasteArea').focus();
            }, 350);
        }
    });

    // クリップボードペーストエリアのイベント
    $('#clipboardPasteArea').on('paste', function(e) {
        handleClipboardPaste(e.originalEvent);
    });

    // クリップボードペーストエリアのクリックでフォーカス
    $('#clipboardPasteArea').on('click', function() {
        $(this).focus();
    });

    // クリップボード画像をクリア
    $('#clearClipboardImage').on('click', function() {
        clearClipboardImage();
    });

    // クリップボードフォーム送信
    $('#clipboardUploadForm').on('submit', function(e) {
        e.preventDefault();
        uploadClipboardImage();
    });

    // クリップボードキャンセル
    $('#clipboardCancelBtn').on('click', function() {
        clearClipboardImage();
        $('#clipboardUploadForm')[0].reset();
        $('#clipboardUploadSection').slideUp(300);
        $('#clipboardToggleIcon').removeClass('bi-chevron-up').addClass('bi-chevron-down');
    });

    // 画像プレビュー
    $('#image').on('change', function(e) {
        const file = e.target.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                $('#imagePreview').attr('src', e.target.result).show();
            };
            reader.readAsDataURL(file);
        } else {
            $('#imagePreview').hide();
        }
    });

    // 編集モーダル：差し替え画像プレビュー
    $('#editImageFile').on('change', function(e) {
        const file = e.target.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                $('#editImageReplacePreviewImg').attr('src', e.target.result);
                $('#editImageReplacePreview').show();
            };
            reader.readAsDataURL(file);
        } else {
            $('#editImageReplacePreview').hide();
        }
    });

    // アップロードフォーム送信
    $('#uploadForm').on('submit', function(e) {
        e.preventDefault();

        const formData = new FormData(this);

        // チェックボックスの値を明示的に設定（チェックされていない場合も '0' として送信）
        formData.set('is_sensitive', $('#isSensitive').is(':checked') ? '1' : '0');
        formData.set('is_visible', $('#isVisible').is(':checked') ? '1' : '0');

        const $submitBtn = $(this).find('button[type="submit"]');
        const originalText = $submitBtn.html();

        // ボタンを無効化
        $submitBtn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>アップロード中...');
        $('#uploadAlert').addClass('d-none');
        $('#uploadError').addClass('d-none');

        $.ajax({
            url: '/' + ADMIN_PATH + '/api/upload.php',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    // 成功メッセージ
                    $('#uploadAlert').text(response.message || '投稿が作成されました').removeClass('d-none');

                    // フォームをリセット
                    $('#uploadForm')[0].reset();
                    $('#imagePreview').hide();

                    // 投稿一覧を再読み込み
                    loadPosts();

                    // 3秒後にメッセージを消す
                    setTimeout(function() {
                        $('#uploadAlert').addClass('d-none');
                    }, 3000);
                } else {
                    $('#uploadError').text(response.error || 'アップロードに失敗しました').removeClass('d-none');
                }
            },
            error: function(xhr) {
                let errorMsg = 'サーバーエラーが発生しました';
                if (xhr.responseJSON && xhr.responseJSON.error) {
                    errorMsg = xhr.responseJSON.error;
                }
                $('#uploadError').text(errorMsg).removeClass('d-none');
            },
            complete: function() {
                // ボタンを有効化
                $submitBtn.prop('disabled', false).html(originalText);
            }
        });
    });

    // 一括アップロード: プレビュー表示
    $('#bulkImages').on('change', function(e) {
        const files = e.target.files;
        const $previewList = $('#bulkPreviewList');
        $previewList.empty();

        if (files.length > 0) {
            Array.from(files).forEach(function(file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    $previewList.append(`
                        <div class="col-4 col-md-3">
                            <img src="${e.target.result}" class="img-thumbnail" data-inline-style="width: 100%; height: 100px; object-fit: cover;">
                        </div>
                    `);
                };
                reader.readAsDataURL(file);
            });
        }
    });

    // 一括アップロードフォーム送信
    $('#bulkUploadForm').on('submit', function(e) {
        e.preventDefault();

        const formData = new FormData(this);
        const $submitBtn = $(this).find('button[type="submit"]');
        const originalText = $submitBtn.html();

        // ボタンを無効化
        $submitBtn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>アップロード中...');
        $('#bulkUploadAlert').addClass('d-none');
        $('#bulkUploadError').addClass('d-none');

        $.ajax({
            url: '/' + ADMIN_PATH + '/api/bulk_upload.php',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    // 成功メッセージ
                    const msg = `${response.success_count}件の画像をアップロードしました（非表示状態）`;
                    $('#bulkUploadAlert').html(msg).removeClass('d-none');

                    // エラーがあれば表示
                    if (response.error_count > 0) {
                        let errorHtml = `${response.error_count}件の画像が失敗しました:<br>`;
                        response.results.forEach(function(result) {
                            if (!result.success) {
                                errorHtml += `- ${result.filename}: ${result.error}<br>`;
                            }
                        });
                        $('#bulkUploadError').html(errorHtml).removeClass('d-none');
                    }

                    // フォームをリセット
                    $('#bulkUploadForm')[0].reset();
                    $('#bulkPreviewList').empty();

                    // 投稿一覧を再読み込み
                    loadPosts();

                    // 5秒後にメッセージを消す
                    setTimeout(function() {
                        $('#bulkUploadAlert').addClass('d-none');
                        $('#bulkUploadError').addClass('d-none');
                    }, 5000);
                } else {
                    $('#bulkUploadError').text(response.error || '一括アップロードに失敗しました').removeClass('d-none');
                }
            },
            error: function(xhr) {
                let errorMsg = 'サーバーエラーが発生しました';
                if (xhr.responseJSON && xhr.responseJSON.error) {
                    errorMsg = xhr.responseJSON.error;
                }
                $('#bulkUploadError').text(errorMsg).removeClass('d-none');
            },
            complete: function() {
                // ボタンを有効化
                $submitBtn.prop('disabled', false).html(originalText);
            }
        });
    });

    // 編集モーダルの保存ボタン
    $('#saveEditBtn').on('click', function() {
        savePost();
    });

    // 前の投稿へ移動
    $('#prevPostBtn').on('click', function() {
        if (currentEditingIndex > 0) {
            const prevPost = allPosts[currentEditingIndex - 1];
            editPost(prevPost.id);
        }
    });

    // 次の投稿へ移動
    $('#nextPostBtn').on('click', function() {
        if (currentEditingIndex < allPosts.length - 1) {
            const nextPost = allPosts[currentEditingIndex + 1];
            editPost(nextPost.id);
        }
    });

    // 全選択ボタン
    $('#selectAllBtn').on('click', function() {
        const $checkboxes = $('.post-select-checkbox');
        const allChecked = $checkboxes.length > 0 && $checkboxes.filter(':checked').length === $checkboxes.length;

        $checkboxes.prop('checked', !allChecked);
        updateBulkActionButtons();

        // ボタンのテキストを切り替え
        if (!allChecked) {
            $(this).html('<i class="bi bi-square me-1"></i>全解除');
        } else {
            $(this).html('<i class="bi bi-check-square me-1"></i>全選択');
        }
    });

    // 一括公開ボタン
    $('#bulkPublishBtn').on('click', function() {
        bulkUpdateVisibility(1);
    });

    // 一括非公開ボタン
    $('#bulkUnpublishBtn').on('click', function() {
        bulkUpdateVisibility(0);
    });
});

/**
 * 投稿一覧を読み込み
 */
function loadPosts(append = false) {
    if (!append) {
        // 初回読み込みの場合はリセット
        postsOffset = 0;
        allPosts = [];
    }

    $.ajax({
        url: '/' + ADMIN_PATH + '/api/posts.php',
        type: 'GET',
        data: {
            limit: postsLimit,
            offset: postsOffset
        },
        dataType: 'json',
        success: function(response) {
            if (response.success && response.posts) {
                totalPostsCount = response.total || 0;

                if (append) {
                    // 追加読み込みの場合
                    allPosts = allPosts.concat(response.posts);
                } else {
                    // 初回読み込みの場合
                    allPosts = response.posts;
                }

                renderPosts(allPosts, response.hasMore);
                postsOffset += response.posts.length;
            } else {
                $('#postsList').html('<div class="text-center p-4 text-muted">投稿がありません</div>');
            }
        },
        error: function() {
            $('#postsList').html('<div class="text-center p-4 text-danger">投稿の読み込みに失敗しました</div>');
        }
    });
}

/**
 * さらに投稿を読み込み
 */
function loadMorePosts() {
    loadPosts(true);
}

/**
 * 投稿一覧をレンダリング（グリッドレイアウト）
 */
function renderPosts(posts, hasMore = false) {
    if (posts.length === 0) {
        $('#postsList').html('<div class="text-center p-4 text-muted">投稿がありません</div>');
        // Bootstrap の .d-none は !important なので、display の直接操作では効かない。
        // クラスで表示/非表示を切り替えることで確実に制御する。
        $('#bulkActionButtons').addClass('d-none');
        return;
    }

    // 一括操作ボタンを表示（d-none を外す）
    $('#bulkActionButtons').removeClass('d-none');

    let html = '<div class="posts-grid">';
    posts.forEach(function(post) {
        const thumbPath = post.thumb_path || post.image_path || '';
        const tags = post.tags || '';
        const detail = post.detail || '';
        const createdAt = new Date(post.created_at).toLocaleDateString('ja-JP');
        const isSensitive = post.is_sensitive == 1;
        const isVisible = post.is_visible == 1;
        const isGroupPost = post.post_type == 1;

        // post_typeに応じて編集関数を切り替え
        const editFunction = isGroupPost ? 'editGroupPost' : 'editPost';
        const deleteFunction = isGroupPost ? 'deleteGroupPost' : 'deletePost';
        const shareFunction = isGroupPost ? 'shareGroupPostToSNS' : 'shareToSNS';

        html += `
            <div class="post-card ${!isVisible ? 'post-card-hidden' : ''}" data-id="${post.id}">
                <div class="post-card-checkbox">
                    <input type="checkbox" class="form-check-input post-select-checkbox" data-post-id="${post.id}">
                </div>
                <div class="post-card-image">
                    <img src="/${thumbPath}" alt="${escapeHtml(post.title)}" onerror="this.src=(window.PLACEHOLDER_URL || '/uploads/thumbs/placeholder.webp')">
                    ${isGroupPost && post.image_count ? '<span class="badge bg-info position-absolute top-0 end-0 m-2"><i class="bi bi-images"></i> ' + post.image_count + '</span>' : ''}
                    <div class="post-card-overlay">
                        <div class="btn-group" role="group">
                            <button class="btn btn-sm btn-primary post-edit-btn" data-post-id="${post.id}" data-post-type="${isGroupPost ? 'group' : 'single'}" title="編集">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <button class="btn btn-sm btn-success post-share-btn" data-post-id="${post.id}" data-post-title="${escapeHtml(post.title).replace(/\"/g, '&quot;')}" data-post-sensitive="${isSensitive}" title="SNS共有">
                                <i class="bi bi-share"></i>
                            </button>
                            <button class="btn btn-sm btn-danger post-delete-btn" data-post-id="${post.id}" data-post-type="${isGroupPost ? 'group' : 'single'}" data-post-title="${escapeHtml(post.title).replace(/\"/g, '&quot;')}" title="削除">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                    </div>
                </div>
                <div class="post-card-body">
                    <div class="post-card-title">${escapeHtml(post.title)}</div>
                    ${detail ? '<div class="post-card-description">' + escapeHtml(detail) + '</div>' : ''}
                    <div class="post-card-meta">
                        ${!isVisible ? '<span class="badge bg-warning text-dark me-1"><i class="bi bi-eye-slash"></i> 非表示</span>' : ''}
                        ${isSensitive ? '<span class="badge bg-danger me-1">NSFW</span>' : ''}
                        ${isGroupPost ? '<span class="badge bg-primary me-1"><i class="bi bi-images"></i> グループ</span>' : ''}
                        ${tags ? '<span class="badge bg-secondary me-1">' + escapeHtml(tags) + '</span>' : ''}
                    </div>
                    <div class="post-card-date text-muted">${createdAt}</div>
                </div>
            </div>
        `;
    });
    html += '</div>';

    // 件数表示と「もっと見る」ボタン
    html += '<div class="posts-footer text-center mt-3 mb-3">';
    html += `<p class="text-muted mb-2">表示中: ${posts.length}件 / 全${totalPostsCount}件</p>`;
    if (hasMore) {
        html += '<button class="btn btn-outline-primary" onclick="loadMorePosts()"><i class="bi bi-arrow-down-circle me-2"></i>もっと見る</button>';
    }
    html += '</div>';

    $('#postsList').html(html);
    // 各ボタン・チェックボックスのイベントを設定
    if (typeof setupPostEventListeners === 'function') {
        setupPostEventListeners();
    }
}

/**
 * 投稿カードのイベントリスナーを設定
 */
function setupPostEventListeners() {
    // 編集ボタン
    $('.post-edit-btn').off('click').on('click', function() {
        const postId = $(this).data('post-id');
        const postType = $(this).data('post-type');
        if (postType === 'group') {
            if (typeof editGroupPost === 'function') {
                editGroupPost(postId);
            }
        } else {
            editPost(postId);
        }
    });

    // 共有ボタン
    $('.post-share-btn').off('click').on('click', function() {
        const postId = $(this).data('post-id');
        const title = $(this).data('post-title');
        const isSensitive = $(this).data('post-sensitive');
        if (typeof shareToSNS === 'function') {
            shareToSNS(postId, title, isSensitive);
        }
    });

    // 削除ボタン
    $('.post-delete-btn').off('click').on('click', function() {
        const postId = $(this).data('post-id');
        const postType = $(this).data('post-type');
        const title = $(this).data('post-title');
        if (postType === 'group') {
            if (typeof deleteGroupPost === 'function') {
                deleteGroupPost(postId, title);
                return;
            }
        }
        // 単一投稿の削除
        deletePost(postId);
    });

    // チェックボックス（バルク操作）
    $('.post-select-checkbox').off('change').on('change', function() {
        updateBulkActionButtons();
    });
}

/**
 * 投稿を削除
 */
function deletePost(postId) {
    if (!confirm('この投稿を削除してもよろしいですか?')) {
        return;
    }

    const csrfToken = $('input[name="csrf_token"]').val();

    $.ajax({
        url: '/' + ADMIN_PATH + '/api/posts.php?id=' + postId,
        type: 'POST',
        data: {
            _method: 'DELETE',
            csrf_token: csrfToken
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                // 投稿一覧を再読み込み
                loadPosts();

                // 成功メッセージ
                $('#uploadAlert').text(response.message || '投稿が削除されました').removeClass('d-none');
                setTimeout(function() {
                    $('#uploadAlert').addClass('d-none');
                }, 3000);
            } else {
                alert(response.error || '削除に失敗しました');
            }
        },
        error: function(xhr) {
            let errorMsg = 'サーバーエラーが発生しました';
            if (xhr.responseJSON && xhr.responseJSON.error) {
                errorMsg = xhr.responseJSON.error;
            }
            alert(errorMsg);
        }
    });
}

/**
 * 投稿を編集
 */
function editPost(postId) {
    // 現在の投稿のインデックスを保存
    currentEditingIndex = allPosts.findIndex(p => p.id == postId);

    // 投稿データを取得（管理画面用API）
    $.ajax({
        url: '/' + ADMIN_PATH + '/api/posts.php?id=' + postId,
        type: 'GET',
        dataType: 'json',
        success: function(response) {
            if (response.success && response.post) {
                const post = response.post;

                // フォームに値を設定
                $('#editPostId').val(post.id);
                $('#editTitle').val(post.title || '');
                $('#editTags').val(post.tags || '');
                $('#editDetail').val(post.detail || '');

                // センシティブフラグを設定
                $('#editIsSensitive').prop('checked', post.is_sensitive == 1);

                // 表示順序を設定
                $('#editSortOrder').val(post.sort_order || 0);

                // 表示/非表示フラグを設定
                $('#editIsVisible').prop('checked', post.is_visible == 1);

                // 画像プレビュー
                const imagePath = post.image_path || post.thumb_path || '';
                if (imagePath) {
                    $('#editImagePreview').attr('src', '/' + imagePath).show();
                } else {
                    $('#editImagePreview').hide();
                }

                // アラートをリセット
                $('#editAlert').addClass('d-none');
                $('#editError').addClass('d-none');

                // 画像差し替えフィールドをリセット
                $('#editImageFile').val('');
                $('#editImageReplacePreview').hide();

                // ナビゲーションボタンの有効/無効を設定
                updateNavigationButtons();

                // モーダルを表示
                if (window.editModal) {
                    window.editModal.show();
                }
            } else {
                alert('投稿データの取得に失敗しました');
            }
        },
        error: function(xhr) {
            let errorMsg = '投稿データの取得に失敗しました';
            if (xhr.responseJSON && xhr.responseJSON.error) {
                errorMsg = xhr.responseJSON.error;
            }
            alert(errorMsg);
        }
    });
}

/**
 * ナビゲーションボタンの有効/無効を更新
 */
function updateNavigationButtons() {
    const hasPrev = currentEditingIndex > 0;
    const hasNext = currentEditingIndex < allPosts.length - 1;

    $('#prevPostBtn').prop('disabled', !hasPrev);
    $('#nextPostBtn').prop('disabled', !hasNext);
}

/**
 * 特定の投稿だけを更新（ページ位置を保持）
 */
function updateSinglePost(postId) {
    $.ajax({
        url: '/' + ADMIN_PATH + '/api/posts.php?id=' + postId,
        type: 'GET',
        dataType: 'json',
        success: function(response) {
            if (response.success && response.post) {
                const updatedPost = response.post;

                // allPosts配列内の該当投稿を更新
                const index = allPosts.findIndex(p => p.id == postId);
                if (index !== -1) {
                    allPosts[index] = updatedPost;
                }

                // DOM内の該当カードを更新
                updatePostCard(updatedPost);
            }
        },
        error: function() {
            // エラー時は安全のため全体を再読み込み
            console.warn('Failed to update single post, reloading all posts');
            loadPosts();
        }
    });
}

/**
 * 投稿カードのDOMを更新
 */
function updatePostCard(post) {
    const $card = $(`.post-card[data-id="${post.id}"]`);
    if ($card.length === 0) return;

    const thumbPath = post.thumb_path || post.image_path || '';
    const tags = post.tags || '';
    const detail = post.detail || '';
    const isSensitive = post.is_sensitive == 1;
    const isVisible = post.is_visible == 1;
    const isGroupPost = post.post_type == 1;

    // カードの表示状態を更新
    if (isVisible) {
        $card.removeClass('post-card-hidden');
    } else {
        $card.addClass('post-card-hidden');
    }

    // 画像を更新
    $card.find('.post-card-image img').attr('src', '/' + thumbPath);

    // グループ投稿の画像数バッジを更新
    const $imageBadge = $card.find('.post-card-image .badge.bg-info');
    if (isGroupPost && post.image_count) {
        if ($imageBadge.length > 0) {
            $imageBadge.html(`<i class="bi bi-images"></i> ${post.image_count}`);
        } else {
            $card.find('.post-card-image').append(`<span class="badge bg-info position-absolute top-0 end-0 m-2"><i class="bi bi-images"></i> ${post.image_count}</span>`);
        }
    } else {
        $imageBadge.remove();
    }

    // タイトルを更新
    $card.find('.post-card-title').text(post.title);

    // 詳細を更新
    const $description = $card.find('.post-card-description');
    if (detail) {
        if ($description.length > 0) {
            $description.text(detail);
        } else {
            $card.find('.post-card-title').after(`<div class="post-card-description">${escapeHtml(detail)}</div>`);
        }
    } else {
        $description.remove();
    }

    // メタ情報（バッジ）を更新
    const $meta = $card.find('.post-card-meta');
    $meta.empty();

    // 表示/非表示バッジ
    if (!isVisible) {
        $meta.append('<span class="badge bg-warning text-dark me-1"><i class="bi bi-eye-slash"></i> 非表示</span>');
    }

    // センシティブバッジ
    if (isSensitive) {
        $meta.append('<span class="badge bg-danger me-1">NSFW</span>');
    }

    // グループ投稿バッジ
    if (isGroupPost) {
        $meta.append('<span class="badge bg-primary me-1"><i class="bi bi-images"></i> グループ</span>');
    }

    // タグバッジ
    if (tags) {
        $meta.append(`<span class="badge bg-secondary me-1">${escapeHtml(tags)}</span>`);
    }
}

/**
 * 投稿を保存
 */
function savePost() {
    const $saveBtn = $('#saveEditBtn');
    const originalText = $saveBtn.html();

    // ボタンを無効化
    $saveBtn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>保存中...');
    $('#editAlert').addClass('d-none');
    $('#editError').addClass('d-none');

    // 投稿IDを取得
    const postId = $('#editPostId').val();

    // FormDataを作成（画像ファイルを含める）
    const formData = new FormData($('#editForm')[0]);
    formData.append('_method', 'PUT');

    // 画像ファイルが選択されている場合は追加
    const imageFile = $('#editImageFile')[0].files[0];
    if (imageFile) {
        formData.append('image', imageFile);
    }

    // チェックボックスの値を明示的に設定
    formData.set('is_sensitive', $('#editIsSensitive').is(':checked') ? '1' : '0');
    formData.set('is_visible', $('#editIsVisible').is(':checked') ? '1' : '0');

    // 表示順序を設定
    formData.set('sort_order', $('#editSortOrder').val() || '0');

    $.ajax({
        url: '/' + ADMIN_PATH + '/api/posts.php?id=' + postId,
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                // 成功メッセージ
                $('#editAlert').text(response.message || '投稿が更新されました').removeClass('d-none');

                // 投稿一覧を再読み込みせず、該当の投稿だけを更新
                updateSinglePost(postId);

                // 画像ファイル入力とプレビューをクリア
                $('#editImageFile').val('');
                $('#editImageReplacePreview').hide();

                // 2秒後にモーダルを閉じる
                //setTimeout(function() {
                //    if (editModal) {
                //        editModal.hide();
                //    }
                //    $('#editAlert').addClass('d-none');
                //}, 1500);

                // 成功メッセージをトップにも表示
                $('#uploadAlert').text(response.message || '投稿が更新されました').removeClass('d-none');
                setTimeout(function() {
                    $('#uploadAlert').addClass('d-none');
                }, 3000);
            } else {
                $('#editError').text(response.error || '保存に失敗しました').removeClass('d-none');
            }
        },
        error: function(xhr) {
            let errorMsg = 'サーバーエラーが発生しました';
            if (xhr.responseJSON && xhr.responseJSON.error) {
                errorMsg = xhr.responseJSON.error;
            }
            $('#editError').text(errorMsg).removeClass('d-none');
        },
        complete: function() {
            // ボタンを有効化
            $saveBtn.prop('disabled', false).html(originalText);
        }
    });
}

/**
 * 一括操作ボタンの有効/無効を更新
 */
function updateBulkActionButtons() {
    const $checked = $('.post-select-checkbox:checked');
    const count = $checked.length;

    // 選択数に応じてボタンを有効/無効化
    $('#bulkPublishBtn').prop('disabled', count === 0);
    $('#bulkUnpublishBtn').prop('disabled', count === 0);

    // 全選択ボタンのテキストを更新
    const $allCheckboxes = $('.post-select-checkbox');
    const allChecked = $allCheckboxes.length > 0 && count === $allCheckboxes.length;

    if (allChecked) {
        $('#selectAllBtn').html('<i class="bi bi-square me-1"></i>全解除');
    } else {
        $('#selectAllBtn').html('<i class="bi bi-check-square me-1"></i>全選択');
    }

    // 選択件数バッジを更新
    const $selectionCount = $('#selectionCount');
    if (count > 0) {
        $selectionCount.text(`${count}件選択中`).show();
    } else {
        $selectionCount.hide();
    }
}

/**
 * 一括で公開/非公開を更新
 */
function bulkUpdateVisibility(visibility) {
    const $checked = $('.post-select-checkbox:checked');
    const postIds = [];

    $checked.each(function() {
        postIds.push($(this).data('post-id'));
    });

    if (postIds.length === 0) {
        alert('投稿を選択してください');
        return;
    }

    const action = visibility === 1 ? '公開' : '非公開';
    if (!confirm(`選択した${postIds.length}件の投稿を${action}にしますか？`)) {
        return;
    }

    const csrfToken = $('input[name="csrf_token"]').val();

    // ボタンを無効化
    const $publishBtn = $('#bulkPublishBtn');
    const $unpublishBtn = $('#bulkUnpublishBtn');
    const originalPublishText = $publishBtn.html();
    const originalUnpublishText = $unpublishBtn.html();

    $publishBtn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>処理中...');
    $unpublishBtn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>処理中...');

    $.ajax({
        url: '/' + ADMIN_PATH + '/api/posts.php',
        type: 'POST',
        data: {
            _method: 'PATCH',
            post_ids: postIds,
            is_visible: visibility,
            csrf_token: csrfToken
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                // 成功メッセージ
                $('#uploadAlert').text(response.message || `${postIds.length}件の投稿を${action}にしました`).removeClass('d-none');

                // 投稿一覧を再読み込み
                loadPosts();

                // 3秒後にメッセージを消す
                setTimeout(function() {
                    $('#uploadAlert').addClass('d-none');
                }, 3000);
            } else {
                $('#uploadError').text(response.error || '一括更新に失敗しました').removeClass('d-none');
                setTimeout(function() {
                    $('#uploadError').addClass('d-none');
                }, 3000);
            }
        },
        error: function(xhr) {
            let errorMsg = 'サーバーエラーが発生しました';
            if (xhr.responseJSON && xhr.responseJSON.error) {
                errorMsg = xhr.responseJSON.error;
            }
            $('#uploadError').text(errorMsg).removeClass('d-none');
            setTimeout(function() {
                $('#uploadError').addClass('d-none');
            }, 3000);
        },
        complete: function() {
            // ボタンを有効化
            $publishBtn.prop('disabled', false).html(originalPublishText);
            $unpublishBtn.prop('disabled', false).html(originalUnpublishText);
            updateBulkActionButtons();
        }
    });
}

/**
 * クリップボードから画像を貼り付け
 */
function handleClipboardPaste(event) {
    const items = event.clipboardData.items;

    for (let i = 0; i < items.length; i++) {
        const item = items[i];

        // 画像アイテムのみを処理
        if (item.type.indexOf('image') !== -1) {
            const blob = item.getAsFile();

            // BlobをFileオブジェクトに変換
            const format = $('#clipboardFormat').val() || 'webp';
            const extension = format === 'jpg' ? 'jpg' : format;
            const timestamp = Date.now();
            const filename = `clipboard_${timestamp}.${extension}`;

            clipboardImageFile = new File([blob], filename, {
                type: blob.type,
                lastModified: Date.now()
            });

            // プレビュー表示
            const reader = new FileReader();
            reader.onload = function(e) {
                $('#clipboardPreviewImg').attr('src', e.target.result);
                $('#clipboardPasteHint').hide();
                $('#clipboardPreview').show();
                $('#clipboardUploadBtn').prop('disabled', false);
            };
            reader.readAsDataURL(blob);

            $('#clipboardError').addClass('d-none');
            break;
        }
    }

    // 画像以外の場合はエラー表示
    if (!clipboardImageFile) {
        $('#clipboardError').text('画像が見つかりません。画像をコピーしてから貼り付けてください。').removeClass('d-none');
    }
}

/**
 * クリップボード画像をクリア
 */
function clearClipboardImage() {
    clipboardImageFile = null;
    $('#clipboardPreviewImg').attr('src', '');
    $('#clipboardPreview').hide();
    $('#clipboardPasteHint').show();
    $('#clipboardUploadBtn').prop('disabled', true);
    $('#clipboardError').addClass('d-none');
    $('#clipboardAlert').addClass('d-none');
}

/**
 * クリップボード画像をアップロード
 */
function uploadClipboardImage() {
    if (!clipboardImageFile) {
        $('#clipboardError').text('画像が選択されていません').removeClass('d-none');
        return;
    }

    const formData = new FormData();
    formData.append('csrf_token', $('input[name="csrf_token"]').val());
    formData.append('title', $('#clipboardTitle').val());
    formData.append('tags', $('#clipboardTags').val());
    formData.append('detail', $('#clipboardDetail').val());
    formData.append('is_sensitive', $('#clipboardIsSensitive').is(':checked') ? '1' : '0');
    formData.append('is_visible', $('#clipboardIsVisible').is(':checked') ? '1' : '0');

    // 選択された形式に応じてファイルを処理
    const format = $('#clipboardFormat').val();

    // 画像を選択された形式に変換
    convertImageFormat(clipboardImageFile, format).then(function(convertedFile) {
        formData.append('image', convertedFile);

        const $submitBtn = $('#clipboardUploadBtn');
        const originalText = $submitBtn.html();

        // ボタンを無効化
        $submitBtn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>アップロード中...');
        $('#clipboardAlert').addClass('d-none');
        $('#clipboardError').addClass('d-none');

        $.ajax({
            url: '/' + ADMIN_PATH + '/api/upload.php',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    // 成功メッセージ
                    $('#clipboardAlert').text(response.message || '投稿が作成されました').removeClass('d-none');

                    // フォームとプレビューをリセット
                    $('#clipboardUploadForm')[0].reset();
                    clearClipboardImage();

                    // 投稿一覧を再読み込み
                    loadPosts();

                    // 3秒後にメッセージを消す
                    setTimeout(function() {
                        $('#clipboardAlert').addClass('d-none');
                    }, 3000);

                    // 成功メッセージをトップにも表示
                    $('#uploadAlert').text(response.message || '投稿が作成されました').removeClass('d-none');
                    setTimeout(function() {
                        $('#uploadAlert').addClass('d-none');
                    }, 3000);
                } else {
                    $('#clipboardError').text(response.error || 'アップロードに失敗しました').removeClass('d-none');
                }
            },
            error: function(xhr) {
                let errorMsg = 'サーバーエラーが発生しました';
                if (xhr.responseJSON && xhr.responseJSON.error) {
                    errorMsg = xhr.responseJSON.error;
                }
                $('#clipboardError').text(errorMsg).removeClass('d-none');
            },
            complete: function() {
                // ボタンを有効化
                $submitBtn.prop('disabled', false).html(originalText);
            }
        });
    }).catch(function(error) {
        $('#clipboardError').text('画像の変換に失敗しました: ' + error.message).removeClass('d-none');
    });
}

/**
 * 画像を指定された形式に変換
 */
function convertImageFormat(file, targetFormat) {
    return new Promise(function(resolve, reject) {
        const reader = new FileReader();

        reader.onload = function(e) {
            const img = new Image();

            img.onload = function() {
                // Canvasで画像を描画
                const canvas = document.createElement('canvas');
                canvas.width = img.width;
                canvas.height = img.height;

                const ctx = canvas.getContext('2d');
                ctx.drawImage(img, 0, 0);

                // 指定された形式で変換
                let mimeType, quality;
                let extension;

                switch (targetFormat) {
                    case 'png':
                        mimeType = 'image/png';
                        quality = 1.0;
                        extension = 'png';
                        break;
                    case 'jpg':
                    case 'jpeg':
                        mimeType = 'image/jpeg';
                        quality = 0.92;
                        extension = 'jpg';
                        break;
                    case 'webp':
                    default:
                        mimeType = 'image/webp';
                        quality = 0.92;
                        extension = 'webp';
                        break;
                }

                // Canvasから Blob を生成
                canvas.toBlob(function(blob) {
                    if (blob) {
                        const timestamp = Date.now();
                        const filename = `clipboard_${timestamp}.${extension}`;
                        const convertedFile = new File([blob], filename, {
                            type: mimeType,
                            lastModified: Date.now()
                        });
                        resolve(convertedFile);
                    } else {
                        reject(new Error('画像の変換に失敗しました'));
                    }
                }, mimeType, quality);
            };

            img.onerror = function() {
                reject(new Error('画像の読み込みに失敗しました'));
            };

            img.src = e.target.result;
        };

        reader.onerror = function() {
            reject(new Error('ファイルの読み込みに失敗しました'));
        };

        reader.readAsDataURL(file);
    });
}

// HTMLのonclick属性から呼び出せるようにグローバルスコープに公開
window.loadPosts = loadPosts;
window.loadMorePosts = loadMorePosts;
window.deletePost = deletePost;
window.editPost = editPost;
