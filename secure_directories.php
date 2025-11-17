<?php
/**
 * 既存のセキュリティディレクトリに.htaccessを追加
 *
 * data、cache、logsなどのディレクトリに.htaccessを配置して
 * 外部からの直接アクセスを防ぐ
 */

declare(strict_types=1);

require_once __DIR__ . '/src/Security/SecurityUtil.php';

// 保護すべきディレクトリのリスト
$directories = [
    __DIR__ . '/data',
    // キャッシュとログを data 以下にまとめる
    __DIR__ . '/data/cache',
    __DIR__ . '/data/log',
    __DIR__ . '/public/data',
];

echo "セキュリティディレクトリの保護を開始します...\n\n";

$successCount = 0;
$failCount = 0;

foreach ($directories as $dir) {
    echo "処理中: {$dir}\n";

    if (ensureSecureDirectory($dir)) {
        echo "  ✓ 成功: .htaccessを配置しました\n";
        $successCount++;
    } else {
        echo "  ✗ 失敗: .htaccessの配置に失敗しました\n";
        $failCount++;
    }

    echo "\n";
}

echo "---\n";
echo "完了: {$successCount}件成功, {$failCount}件失敗\n";

if ($failCount === 0) {
    echo "\n✓ すべてのディレクトリが正しく保護されました！\n";
} else {
    echo "\n⚠ 一部のディレクトリの保護に失敗しました。ログを確認してください。\n";
    exit(1);
}
