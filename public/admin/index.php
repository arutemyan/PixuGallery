<?php

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../src/Security/SecurityUtil.php';
$config = \App\Config\ConfigManager::getInstance()->getConfig();

use App\Security\CsrfProtection;
use App\Utils\PathHelper;

// セッション開始 & 認証チェック（共通化）
\App\Controllers\AdminControllerBase::ensureAuthenticated(true);
// (ensureAuthenticated がリダイレクトまたは継続する)

// タブファイルの直接アクセス防止用の定数
define('ADMIN_TABS_ALLOWED', true);

// 設定確認タブの表示フラグ
$showConfigViewer = $config['admin']['show_config_viewer'] ?? false;

// CSRFトークンを生成
$csrfToken = CsrfProtection::generateToken();
$username = 'Admin';
try {
    if (class_exists('\App\\Services\\Session')) {
        $username = \App\Services\Session::getInstance()->get('admin_username', $username);
    } else {
        $username = $_SESSION['admin_username'] ?? $username;
    }
} catch (Throwable $e) {
    $username = $_SESSION['admin_username'] ?? $username;
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= escapeHtml($csrfToken) ?>">
    <title>管理ダッシュボード - イラストポートフォリオ</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <?php echo \App\Utils\AssetHelper::linkTag('/res/css/admin.css'); ?>
    <?php echo \App\Utils\AssetHelper::linkTag('/res/css/inline-styles.css'); ?>
</head>
<body data-admin-path="<?= escapeHtml(PathHelper::getAdminPath()) ?>">
    <!-- ナビゲーションバー -->
    <nav class="navbar navbar-expand-lg navbar-dark mb-4">
        <div class="container-fluid">
            <a class="navbar-brand" href="<?= PathHelper::getAdminUrl('index.php') ?>">
                <i class="bi bi-palette-fill me-2"></i>管理ダッシュボード
            </a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="/" target="_blank">
                    <i class="bi bi-eye me-1"></i>サイトを表示
                </a>
                <?php
                // ペイント機能へのリンク（機能が有効な場合のみ表示）
                try {
                    $paintEnabled = \App\Utils\FeatureGate::isEnabled('paint');
                } catch (Throwable $e) {
                    $paintEnabled = true;
                }
                if (!empty($paintEnabled)): ?>
                    <a class="nav-link" href="<?= PathHelper::getAdminUrl('paint/index.php') ?>" target="_blank">
                        <i class="bi bi-brush me-1"></i>ペイント
                    </a>
                <?php endif; ?>
                <span class="nav-link">
                    <i class="bi bi-person-circle me-1"></i><?= escapeHtml($username) ?>
                </span>
                <form method="POST" action="<?= PathHelper::getAdminUrl('logout.php') ?>" class="d-inline">
                    <input type="hidden" name="csrf_token" value="<?= escapeHtml($csrfToken) ?>">
                    <button type="submit" class="btn btn-link nav-link text-light no-underline">
                        <i class="bi bi-box-arrow-right me-1"></i>ログアウト
                    </button>
                </form>
            </div>
        </div>
    </nav>

    <div class="container">
        <!-- タブナビゲーション -->
        <ul class="nav nav-tabs mb-4" id="adminTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="posts-tab" data-bs-toggle="tab" data-bs-target="#posts" type="button" role="tab" aria-controls="posts" aria-selected="true">
                    <i class="bi bi-image me-2"></i>投稿（シングル）
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="group-posts-tab" data-bs-toggle="tab" data-bs-target="#group-posts" type="button" role="tab" aria-controls="group-posts" aria-selected="false">
                    <i class="bi bi-images me-2"></i>投稿（グループ）
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="theme-tab" data-bs-toggle="tab" data-bs-target="#theme" type="button" role="tab" aria-controls="theme" aria-selected="false">
                    <i class="bi bi-palette me-2"></i>テーマ設定
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="settings-tab" data-bs-toggle="tab" data-bs-target="#settings" type="button" role="tab" aria-controls="settings" aria-selected="false">
                    <i class="bi bi-gear me-2"></i>サイト設定
                </button>
            </li>
            <?php if ($showConfigViewer): ?>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="config-viewer-tab" data-bs-toggle="tab" data-bs-target="#config-viewer" type="button" role="tab" aria-controls="config-viewer" aria-selected="false">
                    <i class="bi bi-gear-fill me-2"></i>設定確認
                </button>
            </li>
            <?php endif; ?>
        </ul>

        <!-- タブコンテンツ -->
        <div class="tab-content" id="adminTabsContent">
            <!-- 投稿管理タブ -->
            <div class="tab-pane fade show active" id="posts" role="tabpanel" aria-labelledby="posts-tab">
                <?php require __DIR__ . '/tabs/posts.php'; ?>
            </div>

            <!-- グループ投稿管理タブ -->
            <div class="tab-pane fade" id="group-posts" role="tabpanel" aria-labelledby="group-posts-tab">
                <?php require __DIR__ . '/tabs/group_posts.php'; ?>
            </div>

            <!-- テーマ設定タブ -->
            <div class="tab-pane fade" id="theme" role="tabpanel" aria-labelledby="theme-tab">
                <?php require __DIR__ . '/tabs/theme.php'; ?>
            </div>

            <!-- サイト設定タブ -->
            <div class="tab-pane fade" id="settings" role="tabpanel" aria-labelledby="settings-tab">
                <?php require __DIR__ . '/tabs/settings.php'; ?>
            </div>

            <?php if ($showConfigViewer): ?>
            <!-- 設定確認タブ -->
            <div class="tab-pane fade" id="config-viewer" role="tabpanel" aria-labelledby="config-viewer-tab">
                <?php require __DIR__ . '/tabs/config_viewer.php'; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- 編集モーダル -->
    <div class="modal fade" id="editModal" tabindex="-1" aria-labelledby="editModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <div class="d-flex align-items-center w-100">
                        <button type="button" class="btn btn-outline-secondary btn-sm me-3" id="prevPostBtn" title="前の投稿">
                            <i class="bi bi-chevron-left"></i>
                        </button>
                        <h5 class="modal-title mb-0 flex-grow-1" id="editModalLabel">
                            <i class="bi bi-pencil-square me-2"></i>投稿を編集
                        </h5>
                        <button type="button" class="btn btn-outline-secondary btn-sm me-3" id="nextPostBtn" title="次の投稿">
                            <i class="bi bi-chevron-right"></i>
                        </button>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                </div>
                <div class="modal-body">
                    <div id="editAlert" class="alert alert-success d-none" role="alert"></div>
                    <div id="editError" class="alert alert-danger d-none" role="alert"></div>

                    <div class="row">
                        <!-- 左側：編集フォーム -->
                        <div class="col-md-6">
                            <form id="editForm">
                                <input type="hidden" name="csrf_token" value="<?= escapeHtml($csrfToken) ?>">
                                <input type="hidden" id="editPostId" name="id">

                                <div class="mb-3">
                                    <label for="editTitle" class="form-label">タイトル <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="editTitle" name="title" required>
                                </div>

                                <div class="mb-3">
                                    <label for="editTags" class="form-label">タグ（カンマ区切り）</label>
                                    <input type="text" class="form-control" id="editTags" name="tags" placeholder="例: R18, ファンタジー, ドラゴン">
                                </div>

                                <div class="mb-3">
                                    <label for="editDetail" class="form-label">詳細説明</label>
                                    <textarea class="form-control" id="editDetail" name="detail" rows="4"></textarea>
                                </div>

                                <div class="mb-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="editIsSensitive" name="is_sensitive" value="1">
                                        <label class="form-check-label" for="editIsSensitive">
                                            センシティブコンテンツ（18禁）
                                        </label>
                                        <div class="form-text">18歳未満の閲覧に適さないコンテンツの場合はチェックしてください</div>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label for="editSortOrder" class="form-label">表示順序</label>
                                    <input type="number" class="form-control" id="editSortOrder" name="sort_order" value="0">
                                    <div class="form-text">
                                        0: 通常（作成日時順）<br>
                                        プラス値: 優先度アップ（前方に表示）<br>
                                        マイナス値: 優先度ダウン（後方に表示）
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="editIsVisible" name="is_visible" value="1" checked>
                                        <label class="form-check-label" for="editIsVisible">
                                            <strong>公開ページに表示する</strong>
                                        </label>
                                        <div class="form-text">オフにすると、この投稿は管理画面でのみ表示されます</div>
                                    </div>
                                </div>
                            </form>
                        </div>

                        <!-- 右側：画像プレビュー -->
                        <div class="col-md-6">
                            <div class="sticky-top sticky-top--top20">
                                <label class="form-label">画像</label>
                                <div class="edit-image-preview-container mb-3">
                                    <img id="editImagePreview" alt="画像プレビュー" class="img-fluid rounded">
                                </div>

                                <!-- 画像差し替え -->
                                <div class="mb-3">
                                    <label for="editImageFile" class="form-label">
                                        <i class="bi bi-image me-1"></i>画像を差し替え（任意）
                                    </label>
                                    <input type="file" class="form-control" id="editImageFile" accept="image/*">
                                    <div class="form-text">
                                        画像を選択すると、現在の画像が置き換えられます。<br>
                                        選択しない場合は画像は変更されません。
                                    </div>
                                </div>

                                <!-- 差し替え画像のプレビュー -->
                                <div id="editImageReplacePreview" class="d-none">
                                    <label class="form-label text-primary">新しい画像プレビュー</label>
                                    <div class="edit-image-preview-container mb-2">
                                        <img id="editImageReplacePreviewImg" alt="新しい画像プレビュー" class="img-fluid rounded border border-primary">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle me-1"></i>キャンセル
                    </button>
                    <button type="button" class="btn btn-primary" id="saveEditBtn">
                        <i class="bi bi-check-circle me-1"></i>保存
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- JavaScript読み込み（ハイブリッド方式） -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>

    <!-- 共通機能（常に読み込み） -->
    <?php echo \App\Utils\AssetHelper::scriptTag(PathHelper::getAdminUrl('js/admin_common.js')); ?>

    <!-- 投稿タブ（初期表示タブなので最初から読み込み） -->
    <?php echo \App\Utils\AssetHelper::scriptTag(PathHelper::getAdminUrl('js/admin_posts.js')); ?>

    <!-- その他のタブは動的読み込み -->
    <script>
    // スクリプト動的読み込みヘルパー
    const loadedScripts = new Set();
    function loadScript(src) {
        if (loadedScripts.has(src)) {
            return Promise.resolve();
        }
        return new Promise((resolve, reject) => {
            const script = document.createElement('script');
            script.src = src;
            script.onload = () => {
                loadedScripts.add(src);
                resolve();
            };
            script.onerror = reject;
            document.body.appendChild(script);
        });
    }

    // タブ切り替え時に動的読み込み（one()で1回だけ実行）
    $('#group-posts-tab').one('shown.bs.tab', function() {
        loadScript('<?= \App\Utils\AssetHelper::scriptTag(PathHelper::getAdminUrl('js/admin_group_posts.js')) ?>').then(() => {
            // グループ投稿一覧を読み込み
            if (typeof loadGroupPosts === 'function') {
                loadGroupPosts();
            }
        });
    });

    $('#theme-tab').one('shown.bs.tab', function() {
        loadScript('<?= \App\Utils\AssetHelper::scriptTag(PathHelper::getAdminUrl('js/admin_theme.js')) ?>').then(() => {
            // テーマ設定を読み込み
            if (typeof loadThemeSettings === 'function') {
                loadThemeSettings();
            }
            // プレビューを再読み込み
            const iframe = document.getElementById('sitePreview');
            if (iframe) {
                iframe.src = iframe.src;
            }
        });
    });

    $('#settings-tab').one('shown.bs.tab', function() {
        loadScript('<?= \App\Utils\AssetHelper::scriptTag(PathHelper::getAdminUrl('js/admin_settings.js')) ?>');
        // loadSettings()はadmin_settings.js内でDOM Ready時に自動実行される
    });
    </script>

    <?php echo \App\Utils\AssetHelper::scriptTag(PathHelper::getAdminUrl('js/sns-share.js')); ?>
</body>
</html>
