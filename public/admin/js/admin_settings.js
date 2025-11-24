/**
 * 管理画面 - サイト設定タブ JavaScript
 *
 * グローバル変数 (admin-common.jsで定義):
 * - window.ADMIN_PATH
 * - window.CSRF_TOKEN
 */

// グローバル変数をローカルエイリアスとして取得（コードの可読性向上）
const ADMIN_PATH = window.ADMIN_PATH;
const CSRF_TOKEN = window.CSRF_TOKEN;

$(document).ready(function() {
    // サイト設定を読み込み
    loadSettings();

    // OGP画像アップロード
    $('#uploadOgpImage').on('click', function() {
        uploadOgpImage();
    });

    // OGP画像削除
    $('#deleteOgpImage').on('click', function() {
        if (confirm('OGP画像を削除しますか？')) {
            deleteOgpImage();
        }
    });

    // OGP画像プレビュー
    $('#ogpImageFile').on('change', function(e) {
        const file = e.target.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                $('#ogpImagePreview').attr('src', e.target.result).show();
            };
            reader.readAsDataURL(file);
        } else {
            $('#ogpImagePreview').hide();
        }
    });

    // 設定フォーム送信
    $('#settingsForm').on('submit', function(e) {
        e.preventDefault();
        saveSettings();
    });
});

/**
 * サイト設定を読み込み
 */
function loadSettings() {
    $.ajax({
        url: '/' + ADMIN_PATH + '/api/settings.php',
        type: 'GET',
        dataType: 'json',
        success: function(response) {
            if (response.success && response.settings) {
                response.settings.forEach(function(setting) {
                    if (setting.key === 'show_view_count') {
                        $('#showViewCount').prop('checked', setting.value === '1');
                    } else if (setting.key === 'ogp_title') {
                        $('#ogpTitle').val(setting.value || '');
                    } else if (setting.key === 'ogp_description') {
                        $('#ogpDescription').val(setting.value || '');
                    } else if (setting.key === 'ogp_image') {
                        if (setting.value) {
                            $('#ogpImagePreviewImg').attr('src', '/' + setting.value).show();
                            $('#deleteOgpImage').show();
                        } else {
                            $('#ogpImagePreviewImg').hide();
                            $('#deleteOgpImage').hide();
                        }
                    } else if (setting.key === 'twitter_card') {
                        $('#twitterCard').val(setting.value || 'summary_large_image');
                    } else if (setting.key === 'twitter_site') {
                        $('#twitterSite').val(setting.value || '');
                    }
                });
            }
        },
        error: function() {
            console.error('Failed to load settings');
        }
    });
}

/**
 * OGP画像をアップロード
 */
function uploadOgpImage() {
    const file = $('#ogpImageFile')[0].files[0];

    if (!file) {
        alert('画像ファイルを選択してください');
        return;
    }

    const formData = new FormData();
    formData.append('image', file);
    formData.append('csrf_token', $('input[name="csrf_token"]').val());

    const $uploadBtn = $('#uploadOgpImage');
    const originalText = $uploadBtn.html();

    // ボタンを無効化
    $uploadBtn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>アップロード中...');
    $('#settingsAlert').addClass('d-none');

    $.ajax({
        url: '/' + ADMIN_PATH + '/api/ogp-image.php',
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        success: function(response) {
            if (response.success) {
                // 成功メッセージ
                $('#settingsAlert')
                    .addClass('alert-success')
                    .text(response.message || 'OGP画像がアップロードされました')
                    .removeClass('d-none');

                // プレビュー画像を更新
                $('#ogpImagePreviewImg').attr('src', '/' + response.image_path).show();
                $('#deleteOgpImage').show();

                // 設定を再読み込み
                loadSettings();

                // 3秒後にメッセージを消す
                setTimeout(function() {
                    $('#settingsAlert').addClass('d-none');
                }, 3000);
            } else {
                $('#settingsAlert')
                    .addClass('alert-danger')
                    .text(response.error || 'アップロードに失敗しました')
                    .removeClass('d-none');
            }
        },
        error: function(xhr) {
            let errorMsg = 'サーバーエラーが発生しました';
            if (xhr.responseJSON && xhr.responseJSON.error) {
                errorMsg = xhr.responseJSON.error;
            }
            $('#settingsAlert')
                .addClass('alert-danger')
                .text(errorMsg)
                .removeClass('d-none');
        },
        complete: function() {
            // ボタンを有効化
            $uploadBtn.prop('disabled', false).html(originalText);
        }
    });
}

/**
 * OGP画像を削除
 */
function deleteOgpImage() {
    const $deleteBtn = $('#deleteOgpImage');
    const originalText = $deleteBtn.html();

    // ボタンを無効化
    $deleteBtn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>削除中...');
    $('#settingsAlert').addClass('d-none');

    $.ajax({
        url: '/' + ADMIN_PATH + '/api/ogp-image.php',
        type: 'POST',
        data: {
            _method: 'DELETE',
            csrf_token: $('input[name="csrf_token"]').val()
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                // 成功メッセージ
                $('#settingsAlert')
                    .addClass('alert-success')
                    .text(response.message || 'OGP画像が削除されました')
                    .removeClass('d-none');

                // プレビュー画像を非表示
                $('#ogpImagePreviewImg').hide();
                $deleteBtn.hide();

                // 設定を再読み込み
                loadSettings();

                // 3秒後にメッセージを消す
                setTimeout(function() {
                    $('#settingsAlert').addClass('d-none');
                }, 3000);
            } else {
                $('#settingsAlert')
                    .addClass('alert-danger')
                    .text(response.error || '削除に失敗しました')
                    .removeClass('d-none');
            }
        },
        error: function(xhr) {
            let errorMsg = 'サーバーエラーが発生しました';
            if (xhr.responseJSON && xhr.responseJSON.error) {
                errorMsg = xhr.responseJSON.error;
            }
            $('#settingsAlert')
                .addClass('alert-danger')
                .text(errorMsg)
                .removeClass('d-none');
        },
        complete: function() {
            // ボタンを有効化
            $deleteBtn.prop('disabled', false).html(originalText);
        }
    });
}

/**
 * サイト設定を保存
 */
function saveSettings() {
    const showViewCount = $('#showViewCount').is(':checked') ? '1' : '0';
    const ogpTitle = $('#ogpTitle').val();
    const ogpDescription = $('#ogpDescription').val();
    const twitterCard = $('#twitterCard').val();
    const twitterSite = $('#twitterSite').val();
    const csrfToken = $('input[name="csrf_token"]').val();

    $('#settingsAlert').addClass('d-none').removeClass('alert-success alert-danger');

    $.ajax({
        url: '/' + ADMIN_PATH + '/api/settings.php',
        type: 'POST',
        data: {
            show_view_count: showViewCount,
            ogp_title: ogpTitle,
            ogp_description: ogpDescription,
            twitter_card: twitterCard,
            twitter_site: twitterSite,
            csrf_token: csrfToken
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                $('#settingsAlert')
                    .addClass('alert-success')
                    .text(response.message || '設定が保存されました')
                    .removeClass('d-none');

                // 3秒後にメッセージを消す
                setTimeout(function() {
                    $('#settingsAlert').addClass('d-none');
                }, 3000);
            } else {
                $('#settingsAlert')
                    .addClass('alert-danger')
                    .text(response.error || '保存に失敗しました')
                    .removeClass('d-none');
            }
        },
        error: function(xhr) {
            let errorMsg = 'サーバーエラーが発生しました';
            if (xhr.responseJSON && xhr.responseJSON.error) {
                errorMsg = xhr.responseJSON.error;
            }
            $('#settingsAlert')
                .addClass('alert-danger')
                .text(errorMsg)
                .removeClass('d-none');
        }
    });
}
