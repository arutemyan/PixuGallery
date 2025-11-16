<?php
/**
 * 管理画面用メインレイアウト
 *
 * $content: ページ固有のコンテンツ（バッファリング済み）
 * $pageTitle: ページタイトル
 * $csrfToken: CSRFトークン
 */

use App\Utils\PathHelper;

// ユーザー名取得
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

// ペイント機能の有効化チェック
$paintEnabled = true;
try {
    $paintEnabled = \App\Utils\FeatureGate::isEnabled('paint');
} catch (Throwable $e) {
    $paintEnabled = true;
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= escapeHtml($csrfToken ?? '') ?>">
    <title><?= escapeHtml($pageTitle ?? '管理ダッシュボード') ?> - イラストポートフォリオ</title>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">

    <!-- カスタムCSS -->
    <?= \App\Utils\AssetHelper::linkTag('/res/css/admin.css') ?>
    <?= \App\Utils\AssetHelper::linkTag('/res/css/inline-styles.css') ?>

    <!-- 追加CSS -->
    <?php if (!empty($additionalCss)): ?>
        <?php foreach ($additionalCss as $css): ?>
            <?= \App\Utils\AssetHelper::linkTag($css) ?>
        <?php endforeach; ?>
    <?php endif; ?>
</head>
<body <?= $bodyAttributes ?? '' ?>>
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
                <?php if (!empty($paintEnabled)): ?>
                    <a class="nav-link" href="<?= PathHelper::getAdminUrl('paint/index.php') ?>" target="_blank">
                        <i class="bi bi-brush me-1"></i>ペイント
                    </a>
                <?php endif; ?>
                <span class="nav-link">
                    <i class="bi bi-person-circle me-1"></i><?= escapeHtml($username) ?>
                </span>
                <form method="POST" action="<?= PathHelper::getAdminUrl('logout.php') ?>" class="d-inline">
                    <input type="hidden" name="csrf_token" value="<?= escapeHtml($csrfToken ?? '') ?>">
                    <button type="submit" class="btn btn-link nav-link text-light no-underline">
                        <i class="bi bi-box-arrow-right me-1"></i>ログアウト
                    </button>
                </form>
            </div>
        </div>
    </nav>

    <!-- メインコンテンツ -->
    <?= $content ?>

    <!-- Bootstrap JavaScript -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>

    <!-- 追加JavaScript -->
    <?php if (!empty($additionalJs)): ?>
        <?php foreach ($additionalJs as $js): ?>
            <?= \App\Utils\AssetHelper::scriptTag($js) ?>
        <?php endforeach; ?>
    <?php endif; ?>

    <!-- インラインスクリプト -->
    <?php if (!empty($inlineScripts)): ?>
        <?php foreach ($inlineScripts as $script): ?>
            <script nonce="<?= \App\Security\CspMiddleware::getInstance()->getNonce() ?>">
                <?= $script ?>
            </script>
        <?php endforeach; ?>
    <?php endif; ?>
</body>
</html>
