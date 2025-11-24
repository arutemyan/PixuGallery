/**
 * 管理画面共通 JavaScript
 * 全タブで使用される共通機能
 *
 * すべての変数・関数をwindowオブジェクトに配置してグローバルアクセス可能にする
 */

// Get configuration from meta tags and data attributes (CSP compliance)
window.ADMIN_PATH = document.body.dataset.adminPath || '';
window.CSRF_TOKEN = document.querySelector('meta[name="csrf-token"]')?.content || '';

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
