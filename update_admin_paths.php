<?php
/**
 * 管理画面パスの一括更新スクリプト
 *
 * すべてのファイルでハードコードされた /admin/ パスを
 * admin_url() 関数呼び出しに置換します
 */

declare(strict_types=1);

$replacements = [
    // PHPファイルのリダイレクト
    "header('Location: /admin/" => "header('Location: ' . admin_url('",
    'header("Location: /admin/' => 'header("Location: " . admin_url("',

    // JavaScriptのURL
    "url: '/admin/api/" => "url: '/' + ADMIN_PATH + '/api/",
    'url: "/admin/api/' => 'url: "/" + ADMIN_PATH + "/api/',

    // HTMLのリンク
    'href="/admin/' => 'href="<?= admin_url(\'',
    'action="/admin/' => 'action="<?= admin_url(\'',
    'src="/admin/' => 'src="<?= admin_url(\'',
];

$files = [
    'public/admin/index.php',
    'public/admin/auth_check.php',
    'public/setup.php',
    // 他のファイルも追加
];

echo "管理画面パスの更新を開始します...\n\n";

foreach ($files as $file) {
    $fullPath = __DIR__ . '/' . $file;

    if (!file_exists($fullPath)) {
        echo "⚠ スキップ: {$file} (ファイルが存在しません)\n";
        continue;
    }

    $content = file_get_contents($fullPath);
    $originalContent = $content;

    foreach ($replacements as $search => $replace) {
        $content = str_replace($search, $replace, $content);
    }

    if ($content !== $originalContent) {
        file_put_contents($fullPath, $content);
        echo "✓ 更新: {$file}\n";
    } else {
        echo "- 変更なし: {$file}\n";
    }
}

echo "\n完了しました！\n";
