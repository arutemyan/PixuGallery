/**
 * 管理画面 - テーマ設定タブ JavaScript
 *
 * グローバル変数 (admin-common.jsで定義):
 * - window.ADMIN_PATH
 * - window.CSRF_TOKEN
 * - window.parseColorString()
 * - window.rgbToHex()
 * - window.composeRgba()
 */

// グローバル変数をローカルエイリアスとして取得（コードの可読性向上）
const ADMIN_PATH = window.ADMIN_PATH;
const CSRF_TOKEN = window.CSRF_TOKEN;
const parseColorString = window.parseColorString;
const rgbToHex = window.rgbToHex;
const composeRgba = window.composeRgba;

$(document).ready(function() {
    // テーマ設定を読み込み（テーマタブがアクティブな場合のみ）
    if ($('#theme-tab').hasClass('active')) {
        loadThemeSettings();
    }

    // テーマタブがフォーカスされたときにテーマ設定を読み込み
    $('#theme-tab').on('shown.bs.tab', function() {
        loadThemeSettings();
        // プレビューを再読み込み
        const iframe = document.getElementById('sitePreview');
        if (iframe) {
            iframe.src = iframe.src; // iframeを再読み込み
        }
    });

    // 一覧に戻るボタンのプレビュー更新（カラー+アルファ対応）
    $('#backButtonText, #backButtonBgColor, #backButtonBgAlpha, #backButtonTextColor').on('input change', function() {
        updateBackButtonPreview();
    });

    // 詳細ボタン入力の変更を監視
    $('#detailButtonText, #detailButtonBgColor, #detailButtonBgAlpha, #detailButtonTextColor').on('input change', function() {
        updateDetailButtonPreview();
    });

    // リアルタイムプレビュー
    $('#siteTitle, #siteSubtitle, #headerText, #footerText, #primaryColor, #secondaryColor, #accentColor, #backgroundColor, #textColor, #headingColor, #footerBgColor, #footerTextColor, #cardBorderColor, #cardBgColor, #cardShadowOpacity, #linkColor, #linkHoverColor, #tagBgColor, #tagTextColor, #filterActiveBgColor, #filterActiveTextColor').on('input change', function() {
        updateThemePreview();

        // 文字色プレビューを更新
        const textColor = $('#textColor').val();
        $('#textColorPreview').css('color', textColor);

        // タグプレビューを更新
        const tagBgColor = $('#tagBgColor').val();
        const tagTextColor = $('#tagTextColor').val();
        $('#tagColorPreview').css({
            'background-color': tagBgColor,
            'color': tagTextColor
        });

        // フィルタアクティブプレビューを更新
        const filterActiveBgColor = $('#filterActiveBgColor').val();
        const filterActiveTextColor = $('#filterActiveTextColor').val();
        $('#filterActiveColorPreview').css({
            'background-color': filterActiveBgColor,
            'color': filterActiveTextColor
        });

        // カード影の濃さプレビューを更新
        const shadowValue = $('#cardShadowOpacity').val();
        $('#shadowValue').text(shadowValue);
    });

    // iframeロード時にプレビューを更新
    $('#sitePreview').on('load', function() {
        // iframeが完全に読み込まれてからプレビューを適用
        setTimeout(function() {
            updateThemePreview();
        }, 100);
    });

    // レスポンシブプレビュー切り替え
    $('[data-preview-size]').on('click', function() {
        const size = $(this).data('preview-size');
        $('[data-preview-size]').removeClass('active');
        $(this).addClass('active');

        $('#previewFrame').css('max-width', size);

        // アニメーション効果のためのクラス追加
        $('#previewFrame').addClass('preview-resizing');
        setTimeout(function() {
            $('#previewFrame').removeClass('preview-resizing');
        }, 300);
    });

    // ロゴ画像アップロード
    $('#uploadLogo').on('click', function() {
        uploadThemeImage('logo');
    });

    // ヘッダー画像アップロード
    $('#uploadHeader').on('click', function() {
        uploadThemeImage('header');
    });

    // ロゴ画像削除
    $('#deleteLogo').on('click', function() {
        if (confirm('ロゴ画像を削除しますか？')) {
            deleteThemeImage('logo');
        }
    });

    // ヘッダー画像削除
    $('#deleteHeader').on('click', function() {
        if (confirm('背景画像を削除しますか？')) {
            deleteThemeImage('header');
        }
    });

    // ロゴ画像プレビュー
    $('#logoImage').on('change', function(e) {
        const file = e.target.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                $('#logoPreviewImg').attr('src', e.target.result).show();
            };
            reader.readAsDataURL(file);
        }
    });

    // ヘッダー画像プレビュー
    $('#headerImage').on('change', function(e) {
        const file = e.target.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                $('#headerPreviewImg').attr('src', e.target.result).show();
            };
            reader.readAsDataURL(file);
        }
    });

    // テーマフォーム送信
    $('#themeForm').on('submit', function(e) {
        e.preventDefault();

        // Ensure composed bg color is up-to-date before serializing
        updateBackButtonPreview();
        updateDetailButtonPreview();

        const formData = $(this).serialize();
        const $submitBtn = $(this).find('button[type="submit"]');
        const originalText = $submitBtn.html();

        // ボタンを無効化
        $submitBtn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>保存中...');
        $('#themeAlert').addClass('d-none');
        $('#themeError').addClass('d-none');

        $.ajax({
            url: '/' + ADMIN_PATH + '/api/theme.php',
            type: 'POST',
            data: formData + '&_method=PUT',
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    // 成功メッセージ
                    $('#themeAlert').text(response.message || 'テーマ設定が保存されました').removeClass('d-none');

                    // プレビューを更新
                    updateThemePreview();

                    // 3秒後にメッセージを消す
                    setTimeout(function() {
                        $('#themeAlert').addClass('d-none');
                    }, 3000);
                } else {
                    $('#themeError').text(response.error || '保存に失敗しました').removeClass('d-none');
                }
            },
            error: function(xhr) {
                let errorMsg = 'サーバーエラーが発生しました';
                if (xhr.responseJSON && xhr.responseJSON.error) {
                    errorMsg = xhr.responseJSON.error;
                }
                $('#themeError').text(errorMsg).removeClass('d-none');
            },
            complete: function() {
                // ボタンを有効化
                $submitBtn.prop('disabled', false).html(originalText);
            }
        });
    });
});

/**
 * テーマ設定を読み込み
 */
function loadThemeSettings() {
    $.ajax({
        url: '/' + ADMIN_PATH + '/api/theme.php',
        type: 'GET',
        dataType: 'json',
        success: function(response) {
            if (response.success && response.theme) {
                const theme = response.theme;

                // サイト情報
                $('#siteTitle').val(theme.site_title || '');
                $('#siteSubtitle').val(theme.site_subtitle || '');
                $('#siteDescription').val(theme.site_description || '');

                // ヘッダー色
                $('#primaryColor').val(theme.primary_color || '#8B5AFA');
                $('#secondaryColor').val(theme.secondary_color || '#667eea');
                $('#headingColor').val(theme.heading_color || '#ffffff');

                // コンテンツ色
                $('#backgroundColor').val(theme.background_color || '#1a1a1a');
                $('#textColor').val(theme.text_color || '#ffffff');
                $('#accentColor').val(theme.accent_color || '#FFD700');
                $('#linkColor').val(theme.link_color || '#8B5AFA');
                $('#linkHoverColor').val(theme.link_hover_color || '#a177ff');
                $('#tagBgColor').val(theme.tag_bg_color || '#8B5AFA');
                $('#tagTextColor').val(theme.tag_text_color || '#ffffff');
                $('#filterActiveBgColor').val(theme.filter_active_bg_color || '#8B5AFA');
                $('#filterActiveTextColor').val(theme.filter_active_text_color || '#ffffff');

                // カード設定
                $('#cardBgColor').val(theme.card_bg_color || '#252525');
                $('#cardBorderColor').val(theme.card_border_color || '#333333');
                $('#cardShadowOpacity').val(theme.card_shadow_opacity || '0.3');

                // フッター設定
                $('#footerBgColor').val(theme.footer_bg_color || '#2a2a2a');
                $('#footerTextColor').val(theme.footer_text_color || '#cccccc');

                // カスタムHTML
                $('#headerText').val(theme.header_html || '');
                $('#footerText').val(theme.footer_html || '');

                // ナビゲーション設定（一覧に戻るボタン）
                // 空欄（""）はそのまま反映できるように、null/undefined と空文字を区別して扱う
                $('#backButtonText').val((theme.back_button_text !== undefined && theme.back_button_text !== null) ? theme.back_button_text : '一覧に戻る');
                // 背景色は既存の保存値（hex, rgba, 8-digit hexなど）を解析して color + alpha に分解
                const storedBg = theme.back_button_bg_color || '#8B5AFA';
                const parsed = parseColorString(storedBg);
                if (parsed) {
                    $('#backButtonBgColor').val(parsed.hex);
                    $('#backButtonBgAlpha').val(Math.round(parsed.alpha * 100));
                    $('#backButtonBgAlphaValue').text(Math.round(parsed.alpha * 100) + '%');
                    $('#backButtonBgComposed').val(composeRgba(parsed.hex, parsed.alpha));
                } else {
                    $('#backButtonBgColor').val('#8B5AFA');
                    $('#backButtonBgAlpha').val(100);
                    $('#backButtonBgAlphaValue').text('100%');
                    $('#backButtonBgComposed').val('#8B5AFA');
                }
                $('#backButtonTextColor').val(theme.back_button_text_color || '#FFFFFF');

                // 詳細ボタン設定の読み込み（空欄は保存可能）
                $('#detailButtonText').val((theme.detail_button_text !== undefined && theme.detail_button_text !== null) ? theme.detail_button_text : '詳細表示');
                const storedDetailBg = theme.detail_button_bg_color || '#8B5AFA';
                const parsedDetail = parseColorString(storedDetailBg);
                if (parsedDetail) {
                    $('#detailButtonBgColor').val(parsedDetail.hex);
                    $('#detailButtonBgAlpha').val(Math.round(parsedDetail.alpha * 100));
                    $('#detailButtonBgAlphaValue').text(Math.round(parsedDetail.alpha * 100) + '%');
                    $('#detailButtonBgComposed').val(composeRgba(parsedDetail.hex, parsedDetail.alpha));
                } else {
                    $('#detailButtonBgColor').val('#8B5AFA');
                    $('#detailButtonBgAlpha').val(100);
                    $('#detailButtonBgAlphaValue').text('100%');
                    $('#detailButtonBgComposed').val('#8B5AFA');
                }
                $('#detailButtonTextColor').val(theme.detail_button_text_color || '#FFFFFF');

                // 画像プレビュー
                if (theme.logo_image) {
                    $('#logoPreviewImg').attr('src', '/' + theme.logo_image).show();
                    $('#deleteLogo').show();
                } else {
                    $('#logoPreviewImg').hide();
                    $('#deleteLogo').hide();
                }
                if (theme.header_image) {
                    $('#headerPreviewImg').attr('src', '/' + theme.header_image).show();
                    $('#deleteHeader').show();
                } else {
                    $('#headerPreviewImg').hide();
                    $('#deleteHeader').hide();
                }

                // プレビューを更新
                updateThemePreview();
                updateBackButtonPreview();
                updateDetailButtonPreview();
            }
        },
        error: function() {
            console.error('Failed to load theme settings');
        }
    });
}

/**
 * テーマプレビューを更新（リアルタイムiframeプレビュー）
 */
function updateThemePreview() {
    const siteTitle = $('#siteTitle').val() || 'イラストポートフォリオ';
    const siteSubtitle = $('#siteSubtitle').val() || 'Illustration Portfolio';
    const headerText = $('#headerText').val() || siteTitle;
    const footerText = $('#footerText').val() || '© 2025 Portfolio Site. All rights reserved.';
    const primaryColor = $('#primaryColor').val() || '#8B5AFA';
    const secondaryColor = $('#secondaryColor').val() || '#667eea';
    const accentColor = $('#accentColor').val() || '#FFD700';
    const backgroundColor = $('#backgroundColor').val() || '#1a1a1a';
    const textColor = $('#textColor').val() || '#ffffff';
    const headingColor = $('#headingColor').val() || '#ffffff';
    const footerBgColor = $('#footerBgColor').val() || '#2a2a2a';
    const footerTextColor = $('#footerTextColor').val() || '#cccccc';
    const cardBorderColor = $('#cardBorderColor').val() || '#333333';
    const cardBgColor = $('#cardBgColor').val() || '#252525';
    const linkColor = $('#linkColor').val() || '#8B5AFA';
    const linkHoverColor = $('#linkHoverColor').val() || '#a177ff';
    const tagBgColor = $('#tagBgColor').val() || '#8B5AFA';
    const tagTextColor = $('#tagTextColor').val() || '#ffffff';
    const filterActiveBgColor = $('#filterActiveBgColor').val() || '#8B5AFA';
    const filterActiveTextColor = $('#filterActiveTextColor').val() || '#ffffff';

    try {
        // iframeのドキュメントを取得
        const iframe = document.getElementById('sitePreview');
        if (!iframe || !iframe.contentWindow) {
            console.warn('Preview iframe not ready');
            return;
        }

        const iframeDoc = iframe.contentDocument || iframe.contentWindow.document;
        if (!iframeDoc) {
            console.warn('Cannot access iframe document');
            return;
        }

        // CSS変数を更新
        const root = iframeDoc.documentElement;
        if (root) {
            root.style.setProperty('--primary-color', primaryColor);
            root.style.setProperty('--secondary-color', secondaryColor);
            root.style.setProperty('--accent-color', accentColor);
            root.style.setProperty('--background-color', backgroundColor);
            root.style.setProperty('--text-color', textColor);
            root.style.setProperty('--heading-color', headingColor);
            root.style.setProperty('--footer-bg-color', footerBgColor);
            root.style.setProperty('--footer-text-color', footerTextColor);
            root.style.setProperty('--card-border-color', cardBorderColor);
            root.style.setProperty('--card-bg-color', cardBgColor);
            root.style.setProperty('--link-color', linkColor);
            root.style.setProperty('--link-hover-color', linkHoverColor);
            root.style.setProperty('--tag-bg-color', tagBgColor);
            root.style.setProperty('--tag-text-color', tagTextColor);
            root.style.setProperty('--filter-active-bg-color', filterActiveBgColor);
            root.style.setProperty('--filter-active-text-color', filterActiveTextColor);

            // ヘッダーテキストを更新
            const headerH1 = iframeDoc.querySelector('header h1');
            const headerP = iframeDoc.querySelector('header p');
            if (headerH1) {
                headerH1.textContent = headerText || siteTitle;
            }
            if (headerP) {
                headerP.textContent = siteSubtitle;
            }

            // フッターテキストを更新
            const footer = iframeDoc.querySelector('footer p');
            if (footer) {
                footer.innerHTML = footerText.replace(/\n/g, '<br>');
            }
        }
    } catch (error) {
        // クロスオリジン制約でエラーになる場合があるが、同一オリジンなので基本的には問題ない
        console.warn('Preview update error:', error);
    }
}

/**
 * テーマ画像をアップロード
 */
function uploadThemeImage(imageType) {
    const fileInputId = imageType === 'logo' ? '#logoImage' : '#headerImage';
    const file = $(fileInputId)[0].files[0];

    if (!file) {
        alert('画像ファイルを選択してください');
        return;
    }

    const formData = new FormData();
    formData.append('image', file);
    formData.append('image_type', imageType);
    formData.append('csrf_token', $('input[name="csrf_token"]').val());

    const $uploadBtn = imageType === 'logo' ? $('#uploadLogo') : $('#uploadHeader');
    const originalText = $uploadBtn.html();

    // ボタンを無効化
    $uploadBtn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>アップロード中...');

    $.ajax({
        url: '/' + ADMIN_PATH + '/api/theme-image.php',
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        success: function(response) {
            if (response.success) {
                // 成功メッセージ
                $('#themeAlert').text(response.message || '画像がアップロードされました').removeClass('d-none');

                // プレビュー画像を更新
                const previewImgId = imageType === 'logo' ? '#logoPreviewImg' : '#headerPreviewImg';
                $(previewImgId).attr('src', '/' + response.image_path).show();

                // テーマ設定を再読み込み
                loadThemeSettings();

                // 3秒後にメッセージを消す
                setTimeout(function() {
                    $('#themeAlert').addClass('d-none');
                }, 3000);
            } else {
                $('#themeError').text(response.error || 'アップロードに失敗しました').removeClass('d-none');
            }
        },
        error: function(xhr) {
            let errorMsg = 'サーバーエラーが発生しました';
            if (xhr.responseJSON && xhr.responseJSON.error) {
                errorMsg = xhr.responseJSON.error;
            }
            $('#themeError').text(errorMsg).removeClass('d-none');
        },
        complete: function() {
            // ボタンを有効化
            $uploadBtn.prop('disabled', false).html(originalText);
        }
    });
}

/**
 * テーマ画像を削除
 */
function deleteThemeImage(imageType) {
    const $deleteBtn = imageType === 'logo' ? $('#deleteLogo') : $('#deleteHeader');
    const originalText = $deleteBtn.html();

    // ボタンを無効化
    $deleteBtn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>削除中...');

    $.ajax({
        url: '/' + ADMIN_PATH + '/api/theme-image.php',
        type: 'POST',
        data: {
            _method: 'DELETE',
            image_type: imageType,
            csrf_token: $('input[name="csrf_token"]').val()
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                // 成功メッセージ
                $('#themeAlert').text(response.message || '画像が削除されました').removeClass('d-none');

                // プレビュー画像を非表示
                const previewImgId = imageType === 'logo' ? '#logoPreviewImg' : '#headerPreviewImg';
                $(previewImgId).hide();
                $deleteBtn.hide();

                // テーマ設定を再読み込み
                loadThemeSettings();

                // 3秒後にメッセージを消す
                setTimeout(function() {
                    $('#themeAlert').addClass('d-none');
                }, 3000);
            } else {
                $('#themeError').text(response.error || '削除に失敗しました').removeClass('d-none');
            }
        },
        error: function(xhr) {
            let errorMsg = 'サーバーエラーが発生しました';
            if (xhr.responseJSON && xhr.responseJSON.error) {
                errorMsg = xhr.responseJSON.error;
            }
            $('#themeError').text(errorMsg).removeClass('d-none');
        },
        complete: function() {
            // ボタンを有効化
            $deleteBtn.prop('disabled', false).html(originalText);
        }
    });
}

/**
 * 一覧に戻るボタンのプレビューを更新
 */
function updateBackButtonPreview() {
    const text = $('#backButtonText').val();
    const colorHex = $('#backButtonBgColor').val() || '#8B5AFA';
    const alphaPct = parseInt($('#backButtonBgAlpha').val() || '100', 10);
    const alpha = Math.max(0, Math.min(100, alphaPct)) / 100;
    const textColor = $('#backButtonTextColor').val() || '#FFFFFF';

    const composed = composeRgba(colorHex, alpha);
    // set preview and hidden composed value for form submit
    $('#backButtonPreview')
        .text(text)
        .css({
            'background-color': composed,
            'color': textColor
        });
    $('#backButtonBgComposed').val(composed);
    // update alpha display
    $('#backButtonBgAlphaValue').text(Math.round(alpha * 100) + '%');
}

/**
 * 詳細ボタンのプレビューを更新
 */
function updateDetailButtonPreview() {
    const text = $('#detailButtonText').val();
    const colorHex = $('#detailButtonBgColor').val() || '#8B5AFA';
    const alphaPct = parseInt($('#detailButtonBgAlpha').val() || '100', 10);
    const alpha = Math.max(0, Math.min(100, alphaPct)) / 100;
    const textColor = $('#detailButtonTextColor').val() || '#FFFFFF';

    const composed = composeRgba(colorHex, alpha);
    // set preview and hidden composed value for form submit
    $('#detailButtonPreview')
        .text(text || '')
        .css({
            'background-color': composed,
            'color': textColor
        });
    $('#detailButtonBgComposed').val(composed);
    // update alpha display
    $('#detailButtonBgAlphaValue').text(Math.round(alpha * 100) + '%');
}
