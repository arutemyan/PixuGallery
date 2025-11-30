/**
 * 管理画面共通 JavaScript
 * 全タブで使用される共通機能
 *
 * すべての変数・関数をwindowオブジェクトに配置してグローバルアクセス可能にする
 */

// Get configuration from meta tags and data attributes (CSP compliance)
window.ADMIN_PATH = document.body.dataset.adminPath || '';
window.CSRF_TOKEN = document.querySelector('meta[name="csrf-token"]')?.content || '';

// Admin-wide timeouts (ms) — change here to affect all admin UI timeouts
window.ADMIN_ALERT_TIMEOUT_SUCCESS = 3000; // ms
window.ADMIN_ALERT_TIMEOUT_ERROR = 7000; // ms
window.ADMIN_ALERT_TIMEOUT_INFO = 5000; // ms
window.ADMIN_SCRIPT_WAIT_TIMEOUT = 5000; // ms (dynamic script handler wait timeout)
window.ADMIN_AJAX_TIMEOUT = 600000; // ms (default ajax timeout) — increased to 10 minutes for large uploads

// モーダルインスタンス（グローバル）
window.editModal = null;

/**
 * HTMLエスケープ
 */
window.escapeHtml = function(text) {
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return String(text).replace(/[&<>"']/g, function(m) { return map[m]; });
};

/**
 * 色文字列を解析して hex と alpha を返す
 * サポート: #RRGGBB, #RRGGBBAA, rgb(...), rgba(...)
 */
window.parseColorString = function(str) {
    if (!str || typeof str !== 'string') return null;
    str = str.trim();
    // rgba() / rgb()
    const rgbaMatch = str.match(/rgba?\s*\(([^)]+)\)/i);
    if (rgbaMatch) {
        const parts = rgbaMatch[1].split(',').map(p => p.trim());
        const r = parseInt(parts[0], 10);
        const g = parseInt(parts[1], 10);
        const b = parseInt(parts[2], 10);
        const a = parts.length >= 4 ? parseFloat(parts[3]) : 1;
        return {hex: window.rgbToHex(r, g, b), alpha: isNaN(a) ? 1 : a};
    }
    // 8-digit hex #RRGGBBAA
    const hex8 = str.match(/^#([0-9a-fA-F]{8})$/);
    if (hex8) {
        const hex = '#' + hex8[1].slice(0,6);
        const aa = hex8[1].slice(6,8);
        const alpha = parseInt(aa, 16) / 255;
        return {hex: hex, alpha: alpha};
    }
    // 6-digit hex
    const hex6 = str.match(/^#([0-9a-fA-F]{6})$/);
    if (hex6) {
        return {hex: '#' + hex6[1], alpha: 1};
    }
    return null;
};

window.rgbToHex = function(r, g, b) {
    const toHex = (n) => (Math.max(0, Math.min(255, n))).toString(16).padStart(2, '0');
    return '#' + toHex(r) + toHex(g) + toHex(b);
};

window.composeRgba = function(hex, alpha) {
    // hex: #RRGGBB
    const m = hex.match(/^#?([0-9a-fA-F]{6})$/);
    if (!m) return hex;
    const v = m[1];
    const r = parseInt(v.slice(0,2), 16);
    const g = parseInt(v.slice(2,4), 16);
    const b = parseInt(v.slice(4,6), 16);
    return `rgba(${r}, ${g}, ${b}, ${alpha})`;
};

// Bootstrap モーダル初期化（DOM Ready後）
$(document).ready(function() {
    const editModalElement = document.getElementById('editModal');
    if (editModalElement) {
        window.editModal = new bootstrap.Modal(editModalElement);
    }
});

/**
 * Show an admin alert in a target alert element.
 * Options: { type: 'success'|'error'|'info', message: string, timeout: ms|null, target: selector }
 */
window.showAdminAlert = function(options) {
    const opts = Object.assign({type: 'info', message: '', timeout: null, target: null}, options || {});
    // Determine default timeout based on type when not explicitly provided
    if (opts.timeout === null || typeof opts.timeout === 'undefined') {
        if (opts.type === 'success') opts.timeout = window.ADMIN_ALERT_TIMEOUT_SUCCESS;
        else if (opts.type === 'error') opts.timeout = window.ADMIN_ALERT_TIMEOUT_ERROR;
        else opts.timeout = window.ADMIN_ALERT_TIMEOUT_INFO;
    }

    // If a toast container exists, render a Bootstrap toast. Otherwise fall back
    // to the legacy inline alert element behavior for backward compatibility.
    const toastContainer = document.getElementById('adminToastContainer');
    if (toastContainer) {
        try {
            const typeClass = (opts.type === 'error') ? 'danger' : (opts.type === 'success' ? 'success' : 'info');
            const toastId = 'adminToast-' + Date.now() + '-' + Math.floor(Math.random() * 1000);
            const html = '' +
                '<div id="' + toastId + '" class="toast align-items-center text-bg-' + typeClass + ' border-0" role="alert" aria-live="assertive" aria-atomic="true">' +
                  '<div class="d-flex">' +
                    '<div class="toast-body">' + (opts.message || '') + '</div>' +
                    '<button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="閉じる"></button>' +
                  '</div>' +
                '</div>';

            toastContainer.insertAdjacentHTML('beforeend', html);
            const el = document.getElementById(toastId);
            if (el) {
                const toast = new bootstrap.Toast(el, {delay: opts.timeout});
                el.addEventListener('hidden.bs.toast', function() { el.remove(); });
                toast.show();
            }
            return;
        } catch (e) {
            // if toast creation fails, fall back to legacy behavior below
            console.error('showAdminAlert: toast creation failed', e);
        }
    }

    // Legacy fallback: update an inline alert element (keeps existing behavior)
    let selector = opts.target;
    if (!selector) {
        if ($('#settingsAlert').length) selector = '#settingsAlert';
        if ($('#themeAlert').length && $(selector).length === 0) selector = '#themeAlert';
    }
    if (!selector) selector = 'body';

    const $el = $(selector);
    if ($el.length === 0) {
        console[opts.type === 'error' ? 'error' : 'log'](opts.message);
        return;
    }

    $el.removeClass('d-none alert-success alert-danger alert-info');
    if (opts.type === 'success') $el.addClass('alert-success');
    else if (opts.type === 'error') $el.addClass('alert-danger');
    else $el.addClass('alert-info');

    $el.html(opts.message).removeClass('d-none');

    if (opts.timeout && typeof opts.timeout === 'number') {
        setTimeout(function() { $el.addClass('d-none'); }, opts.timeout);
    }
};

/**
 * Ajax wrapper for admin actions.
 * options: same as $.ajax plus `target` (selector) and `suppressDefaultAlert` flag.
 */
window.ajaxAdmin = function(options) {
    const opts = $.extend({dataType: 'json', suppressDefaultAlert: false, target: null, timeout: null}, options || {});
    // default ajax timeout
    if (!opts.timeout) opts.timeout = window.ADMIN_AJAX_TIMEOUT;

    const userSuccess = opts.success;
    const userError = opts.error;

    opts.success = function(response, textStatus, jqXHR) {
        if (!opts.suppressDefaultAlert) {
            if (response && response.success) {
                const msg = response.message || '保存されました';
                window.showAdminAlert({type: 'success', message: msg, target: opts.target});
            } else {
                const err = (response && response.error) ? response.error : 'エラーが発生しました';
                window.showAdminAlert({type: 'error', message: err, target: opts.target});
            }
        }
        if (typeof userSuccess === 'function') userSuccess(response, textStatus, jqXHR);
    };

    opts.error = function(jqXHR, textStatus, errorThrown) {
        if (!opts.suppressDefaultAlert) {
            let err = 'サーバーエラーが発生しました';
            try {
                const json = jqXHR && jqXHR.responseJSON;
                if (json && json.error) err = json.error;
            } catch (e) {}
            window.showAdminAlert({type: 'error', message: err, target: opts.target});
        }
        if (typeof userError === 'function') userError(jqXHR, textStatus, errorThrown);
    };

    return $.ajax(opts);
};

// Delegate settings form submit to prevent accidental full-page POSTs
// This handles the case where the per-tab script (admin_settings.js)
// hasn't been loaded yet when the user clicks 保存. It prevents the
// browser default submit and ensures `saveSettings()` is called.
$(document).on('submit', '#settingsForm', function(e) {
    // If the per-tab handler already exists, let it handle the submit
    // to avoid duplicate calls.
    if (typeof window.saveSettings === 'function') {
        return; // do not preventDefault here; the existing handler will run
    }

    // Otherwise intercept and dynamically load the settings script,
    // then invoke the save helper after load.
    e.preventDefault();

        (function() {
            const src = '/' + (window.ADMIN_PATH || 'admin') + '/js/admin_settings.js';

            // avoid appending the same script multiple times
                if (!document.querySelector('script[src="' + src + '"]')) {
                const script = document.createElement('script');
                script.src = src;
                script.onerror = function() {
                    console.error('Failed to load ' + src);
                    window.showAdminAlert({type: 'error', message: '設定用スクリプトの読み込みに失敗しました。ネットワークを確認してください。', timeout: window.ADMIN_ALERT_TIMEOUT_ERROR});
                };
                // When the script is loaded, try to invoke saveSettings immediately
                script.onload = function() {
                    try {
                        if (typeof window.saveSettings === 'function') {
                            window.saveSettings();
                        }
                    } catch (e) {
                        console.error('saveSettings() threw during onload call:', e);
                        window.showAdminAlert({type: 'error', message: '設定の保存中にエラーが発生しました。コンソールを確認してください。', timeout: window.ADMIN_ALERT_TIMEOUT_ERROR});
                    }
                };
                document.body.appendChild(script);
            }

            // Wait for the saveSettings function to become available (in case
            // the loaded script schedules initialization on DOM ready or has
            // transient runtime errors). Poll for a short timeout.
            const start = Date.now();
            const timeout = window.ADMIN_SCRIPT_WAIT_TIMEOUT; // ms
            (function waitForHandler() {
                if (typeof window.saveSettings === 'function') {
                    try {
                        window.saveSettings();
                    } catch (err) {
                        console.error('saveSettings() threw after dynamic load:', err);
                        window.showAdminAlert({type: 'error', message: '設定の保存中にエラーが発生しました。コンソールを確認してください。', timeout: window.ADMIN_ALERT_TIMEOUT_ERROR});
                    }
                    return;
                }
                if (Date.now() - start > timeout) {
                    console.error('admin_settings.js loaded but saveSettings() not found (timeout)');
                    window.showAdminAlert({type: 'error', message: '設定の保存処理が見つかりません。', timeout: window.ADMIN_ALERT_TIMEOUT_ERROR});
                    return;
                }
                setTimeout(waitForHandler, 50);
            })();
        })();
});

// Also protect the theme form from accidental full-page submits when
// `admin_theme.js` hasn't been loaded yet.
$(document).on('submit', '#themeForm', function(e) {
    // If the per-tab handler already exists, let it handle the submit
    // to avoid duplicate calls.
    if (typeof window.saveThemeSettings === 'function') {
        return; // do not preventDefault here; the existing handler will run
    }

    // Otherwise intercept and dynamically load the theme script,
    // then invoke the save helper after load.
    e.preventDefault();

        (function() {
            const src = '/' + (window.ADMIN_PATH || 'admin') + '/js/admin_theme.js';

                if (!document.querySelector('script[src="' + src + '"]')) {
                const script = document.createElement('script');
                script.src = src;
                script.onerror = function() {
                    console.error('Failed to load ' + src);
                    window.showAdminAlert({type: 'error', message: 'テーマ設定用スクリプトの読み込みに失敗しました。ネットワークを確認してください。', timeout: window.ADMIN_ALERT_TIMEOUT_ERROR});
                };
                // When the script is loaded, try to invoke saveThemeSettings immediately
                script.onload = function() {
                    try {
                        if (typeof window.saveThemeSettings === 'function') {
                            window.saveThemeSettings();
                        }
                    } catch (e) {
                        console.error('saveThemeSettings() threw during onload call:', e);
                        window.showAdminAlert({type: 'error', message: 'テーマ設定の保存中にエラーが発生しました。コンソールを確認してください。', timeout: window.ADMIN_ALERT_TIMEOUT_ERROR});
                    }
                };
                document.body.appendChild(script);
            }

            const start = Date.now();
            const timeout = window.ADMIN_SCRIPT_WAIT_TIMEOUT; // ms
            (function waitForHandler() {
                if (typeof window.saveThemeSettings === 'function') {
                    try {
                        window.saveThemeSettings();
                    } catch (err) {
                        console.error('saveThemeSettings() threw after dynamic load:', err);
                        window.showAdminAlert({type: 'error', message: 'テーマ設定の保存中にエラーが発生しました。コンソールを確認してください。', timeout: window.ADMIN_ALERT_TIMEOUT_ERROR});
                    }
                    return;
                }
                if (Date.now() - start > timeout) {
                    console.error('admin_theme.js loaded but saveThemeSettings() not found (timeout)');
                    window.showAdminAlert({type: 'error', message: 'テーマ設定の保存処理が見つかりません。', timeout: window.ADMIN_ALERT_TIMEOUT_ERROR});
                    return;
                }
                setTimeout(waitForHandler, 50);
            })();
        })();
});
