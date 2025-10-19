<?php

declare(strict_types=1);

/**
 * 既存NSFW投稿のフィルターサムネイル生成マイグレーションスクリプト
 *
 * 使用方法:
 *   php generate_blur_thumbnails.php           - 設定に応じたフィルター画像を生成
 *   php generate_blur_thumbnails.php --force   - 強制再生成
 *   php generate_blur_thumbnails.php --type=blur     - ぼかし効果を生成
 *   php generate_blur_thumbnails.php --type=frosted  - すりガラス効果を生成
 */

require_once __DIR__ . '/../vendor/autoload.php';

use App\Database\Connection;

// NSFW設定を読み込み
$config = require __DIR__ . '/../config/config.php';
$nsfwConfig = $config['nsfw'];
$defaultFilterType = $nsfwConfig['filter_type'];

// コマンドライン引数を確認
$forceRegenerate = in_array('--force', $argv ?? []);
$filterType = $defaultFilterType;

// --type引数を確認
foreach ($argv ?? [] as $arg) {
    if (strpos($arg, '--type=') === 0) {
        $filterType = substr($arg, 7);
        if (!in_array($filterType, ['blur', 'frosted'])) {
            echo "エラー: 無効なフィルタータイプです。'blur' または 'frosted' を指定してください。\n";
            exit(1);
        }
    }
}

$filterLabel = $filterType === 'frosted' ? 'すりガラス' : 'ぼかし';

// フィルタータイプに応じた設定を取得
$filterSettings = [];
if ($filterType === 'frosted') {
    $filterSettings = $nsfwConfig['frosted_settings'];
} else {
    $filterSettings = $nsfwConfig['blur_settings'];
}

echo "==========================================\n";
echo "NSFWフィルターサムネイル生成スクリプト\n";
echo "==========================================\n\n";
echo "フィルタータイプ: {$filterLabel} ({$filterType})\n\n";

if ($forceRegenerate) {
    echo "⚠️  強制再生成モード: 既存の画像を削除して再生成します\n\n";
}

try {
    // データベース接続
    $db = Connection::getInstance();

    // is_sensitive=1の投稿を取得
    $stmt = $db->query("SELECT id, image_path, thumb_path, is_sensitive FROM posts WHERE is_sensitive = 1");
    $posts = $stmt->fetchAll();

    if (empty($posts)) {
        echo "センシティブな投稿が見つかりませんでした。\n";
        exit(0);
    }

    echo "センシティブな投稿: " . count($posts) . "件\n\n";

    $successCount = 0;
    $failCount = 0;
    $skippedCount = 0;

    foreach ($posts as $post) {
        $id = $post['id'];
        $thumbPath = $post['thumb_path'] ?? $post['image_path'];

        if (empty($thumbPath)) {
            echo "[投稿ID: {$id}] スキップ: 画像パスが空です\n";
            $skippedCount++;
            continue;
        }

        // 物理ファイルパスを構築
        $thumbFullPath = __DIR__ . '/../public/' . $thumbPath;

        if (!file_exists($thumbFullPath)) {
            echo "[投稿ID: {$id}] スキップ: 画像ファイルが見つかりません ({$thumbFullPath})\n";
            $skippedCount++;
            continue;
        }

        // フィルター版のパスを生成
        $pathInfo = pathinfo($thumbFullPath);
        $filterSuffix = $filterType === 'frosted' ? '_frosted' : '_blur';
        $filterPath = $pathInfo['dirname'] . '/' . $pathInfo['filename'] . $filterSuffix . '.' . $pathInfo['extension'];

        // 強制再生成モードの場合、既存のファイルを削除
        if ($forceRegenerate && file_exists($filterPath)) {
            unlink($filterPath);
            echo "[投稿ID: {$id}] 既存の{$filterLabel}画像を削除しました\n";
        }

        // すでにフィルター版が存在する場合はスキップ
        if (file_exists($filterPath)) {
            echo "[投稿ID: {$id}] スキップ: {$filterLabel}版が既に存在します\n";
            $skippedCount++;
            continue;
        }

        // フィルター画像を生成
        $image = imagecreatefromwebp($thumbFullPath);
        if ($image === false) {
            echo "[投稿ID: {$id}] 失敗: 画像の読み込みに失敗しました\n";
            $failCount++;
            continue;
        }

        if ($filterType === 'frosted') {
            // すりガラス効果
            $width = imagesx($image);
            $height = imagesy($image);

            // 設定から値を取得
            $blurStrength = $filterSettings['blur_strength'] ?? 10;
            $contrast = $filterSettings['contrast'] ?? 50;
            $brightness = $filterSettings['brightness'] ?? 0;
            $overlayOpacity = $filterSettings['overlay_opacity'] ?? 50;
            $quality = $filterSettings['quality'] ?? 80;

            // 高速ぼかし処理（縮小→拡大方式）
            $blurStrength = max(1, min(20, $blurStrength));
            $smallWidth = max(1, (int)($width / $blurStrength));
            $smallHeight = max(1, (int)($height / $blurStrength));

            $smallImage = imagecreatetruecolor($smallWidth, $smallHeight);
            imagecopyresampled($smallImage, $image, 0, 0, 0, 0, $smallWidth, $smallHeight, $width, $height);
            imagecopyresampled($image, $smallImage, 0, 0, 0, 0, $width, $height, $smallWidth, $smallHeight);
            imagedestroy($smallImage);

            // コントラストと明度調整
            imagefilter($image, IMG_FILTER_CONTRAST, $contrast);
            imagefilter($image, IMG_FILTER_BRIGHTNESS, $brightness);

            // 半透明の白いオーバーレイ（ピクセル単位でアルファブレンディング）
            $overlayOpacity = min(100, max(0, $overlayOpacity)); // 0-100に制限
            for ($y = 0; $y < $height; $y++) {
                for ($x = 0; $x < $width; $x++) {
                    $rgb = imagecolorat($image, $x, $y);
                    $r = ($rgb >> 16) & 0xFF;
                    $g = ($rgb >> 8) & 0xFF;
                    $b = $rgb & 0xFF;

                    // 白とブレンド（overlay_opacity%の白を混ぜる）
                    $r = (int)($r + (255 - $r) * $overlayOpacity / 100);
                    $g = (int)($g + (255 - $g) * $overlayOpacity / 100);
                    $b = (int)($b + (255 - $b) * $overlayOpacity / 100);

                    $newColor = imagecolorallocate($image, $r, $g, $b);
                    imagesetpixel($image, $x, $y, $newColor);
                }
            }

            $result = imagewebp($image, $filterPath, $quality);
        } else {
            // ぼかし効果（従来）
            $blurStrength = $filterSettings['blur_strength'] ?? 10;
            $brightness = $filterSettings['brightness'] ?? -30;
            $quality = $filterSettings['quality'] ?? 75;

            // 高速ぼかし処理
            $blurStrength = max(1, min(20, $blurStrength));
            $smallWidth = max(1, (int)($width / $blurStrength));
            $smallHeight = max(1, (int)($height / $blurStrength));

            $smallImage = imagecreatetruecolor($smallWidth, $smallHeight);
            imagecopyresampled($smallImage, $image, 0, 0, 0, 0, $smallWidth, $smallHeight, $width, $height);
            imagecopyresampled($image, $smallImage, 0, 0, 0, 0, $width, $height, $smallWidth, $smallHeight);
            imagedestroy($smallImage);

            imagefilter($image, IMG_FILTER_BRIGHTNESS, $brightness);
            $result = imagewebp($image, $filterPath, $quality);
        }

        imagedestroy($image);

        if ($result) {
            echo "[投稿ID: {$id}] 成功: {$filterLabel}サムネイルを生成しました\n";
            $successCount++;
        } else {
            echo "[投稿ID: {$id}] 失敗: {$filterLabel}サムネイルの生成に失敗しました\n";
            $failCount++;
        }
    }

    echo "\n==========================================\n";
    echo "処理完了\n";
    echo "==========================================\n";
    echo "成功: {$successCount}件\n";
    echo "失敗: {$failCount}件\n";
    echo "スキップ: {$skippedCount}件\n";
    echo "合計: " . count($posts) . "件\n\n";

    if ($successCount > 0) {
        echo "✅ {$filterLabel}効果の画像が生成されました。\n";
        echo "   config/config.php の ['nsfw']['filter_type'] が '{$filterType}' に設定されていることを確認してください。\n";
    }

} catch (Exception $e) {
    echo "\nエラーが発生しました: " . $e->getMessage() . "\n";
    echo "スタックトレース:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
