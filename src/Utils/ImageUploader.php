<?php

declare(strict_types=1);

namespace App\Utils;

use Exception;

/**
 * 画像アップロードユーティリティクラス
 *
 * アップロードとバルクアップロードで共通のロジックを提供
 */
class ImageUploader
{
    private string $uploadDir;
    private string $thumbDir;
    private int $maxFileSize;
    private array $allowedMimeTypes;

    /**
     * コンストラクタ
     *
     * @param string $uploadDir アップロードディレクトリのパス
     * @param string $thumbDir サムネイルディレクトリのパス
     * @param int $maxFileSize 最大ファイルサイズ（バイト）
     * @param array $allowedMimeTypes 許可するMIMEタイプ
     */
    public function __construct(
        string $uploadDir,
        string $thumbDir,
        int $maxFileSize = 20 * 1024 * 1024,
        array $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp']
    ) {
        $this->uploadDir = $uploadDir;
        $this->thumbDir = $thumbDir;
        $this->maxFileSize = $maxFileSize;
        $this->allowedMimeTypes = $allowedMimeTypes;

        // ディレクトリが存在しない場合は作成
        if (!is_dir($this->uploadDir)) {
            mkdir($this->uploadDir, 0755, true);
        }
        if (!is_dir($this->thumbDir)) {
            mkdir($this->thumbDir, 0755, true);
        }
    }

    /**
     * アップロードされたファイルを検証
     *
     * @param array $file $_FILES配列の要素
     * @return array ['valid' => bool, 'error' => string|null, 'mime_type' => string|null]
     */
    public function validateFile(array $file): array
    {
        // アップロードエラーチェック
        if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
            return [
                'valid' => false,
                'error' => 'アップロードエラー (code: ' . ($file['error'] ?? 'unknown') . ')',
                'mime_type' => null
            ];
        }

        // ファイルサイズチェック
        if ($file['size'] > $this->maxFileSize) {
            $maxMB = $this->maxFileSize / (1024 * 1024);
            return [
                'valid' => false,
                'error' => "ファイルサイズが大きすぎます（最大{$maxMB}MB）",
                'mime_type' => null
            ];
        }

        // 画像形式チェック
        $imageInfo = @getimagesize($file['tmp_name']);
        if ($imageInfo === false) {
            return [
                'valid' => false,
                'error' => '画像ファイルではありません',
                'mime_type' => null
            ];
        }

        $mimeType = $imageInfo['mime'];
        if (!in_array($mimeType, $this->allowedMimeTypes)) {
            return [
                'valid' => false,
                'error' => 'サポートされていない画像形式です',
                'mime_type' => null
            ];
        }

        return [
            'valid' => true,
            'error' => null,
            'mime_type' => $mimeType
        ];
    }

    /**
     * 画像を処理して保存
     *
     * @param string $tmpPath 一時ファイルのパス
     * @param string $mimeType MIMEタイプ
     * @param string $filename 保存するファイル名（拡張子なし）
     * @param bool $createFiltered フィルター版サムネイルを作成するか
     * @param string $filterType フィルタータイプ ('blur' または 'frosted')
     * @param array $filterSettings フィルター設定（オプション）
     * @return array ['success' => bool, 'image_path' => string, 'thumb_path' => string, 'error' => string|null]
     */
    public function processAndSave(string $tmpPath, string $mimeType, string $filename, bool $createFiltered = false, string $filterType = 'blur', array $filterSettings = []): array
    {
        try {
            $webpFilename = $filename . '.webp';
            $imagePath = $this->uploadDir . '/' . $webpFilename;
            $thumbPath = $this->thumbDir . '/' . $webpFilename;

            // 画像を読み込み
            $sourceImage = match($mimeType) {
                'image/jpeg' => imagecreatefromjpeg($tmpPath),
                'image/png' => imagecreatefrompng($tmpPath),
                'image/gif' => imagecreatefromgif($tmpPath),
                'image/webp' => imagecreatefromwebp($tmpPath),
                default => throw new Exception('サポートされていない画像形式です')
            };

            if ($sourceImage === false) {
                throw new Exception('画像の読み込みに失敗しました');
            }

            // WebP形式で保存（元画像）
            if (!imagewebp($sourceImage, $imagePath, 90)) {
                imagedestroy($sourceImage);
                throw new Exception('画像の保存に失敗しました');
            }

            // サムネイルを生成
            $this->createThumbnail($sourceImage, $thumbPath, 600, 600);

            // フィルター版サムネイルを生成
            if ($createFiltered) {
                $filterSuffix = $filterType === 'frosted' ? '_frosted' : '_blur';
                $filterFilename = $filename . $filterSuffix . '.webp';
                $filterPath = $this->thumbDir . '/' . $filterFilename;

                if ($filterType === 'frosted') {
                    $this->createFrostedThumbnail($thumbPath, $filterPath, $filterSettings);
                } else {
                    $this->createBlurredThumbnail($thumbPath, $filterPath, $filterSettings);
                }
            }

            // メモリ解放
            imagedestroy($sourceImage);

            return [
                'success' => true,
                'image_path' => 'uploads/images/' . $webpFilename,
                'thumb_path' => 'uploads/thumbs/' . $webpFilename,
                'error' => null
            ];

        } catch (Exception $e) {
            // エラー時のクリーンアップ
            if (isset($imagePath) && file_exists($imagePath)) {
                unlink($imagePath);
            }
            if (isset($thumbPath) && file_exists($thumbPath)) {
                unlink($thumbPath);
            }

            return [
                'success' => false,
                'image_path' => null,
                'thumb_path' => null,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * サムネイルを生成
     *
     * @param resource $sourceImage 元画像のGDリソース
     * @param string $outputPath 出力パス
     * @param int $maxWidth 最大幅
     * @param int $maxHeight 最大高さ
     */
    private function createThumbnail($sourceImage, string $outputPath, int $maxWidth, int $maxHeight): void
    {
        $originalWidth = imagesx($sourceImage);
        $originalHeight = imagesy($sourceImage);

        // アスペクト比を保持してリサイズ
        if ($originalWidth > $originalHeight) {
            $newWidth = min($maxWidth, $originalWidth);
            $newHeight = (int)($originalHeight * ($newWidth / $originalWidth));
        } else {
            $newHeight = min($maxHeight, $originalHeight);
            $newWidth = (int)($originalWidth * ($newHeight / $originalHeight));
        }

        $thumbImage = imagecreatetruecolor($newWidth, $newHeight);

        // PNG透過対応
        imagealphablending($thumbImage, false);
        imagesavealpha($thumbImage, true);

        imagecopyresampled(
            $thumbImage,
            $sourceImage,
            0, 0, 0, 0,
            $newWidth, $newHeight,
            $originalWidth, $originalHeight
        );

        imagewebp($thumbImage, $outputPath, 85);
        imagedestroy($thumbImage);
    }

    /**
     * ぼかし版サムネイルを生成（NSFW用）
     *
     * @param string $sourcePath 元サムネイルのパス
     * @param string $outputPath 出力パス
     * @param array $settings フィルター設定（blur_strength, brightness, quality）
     */
    private function createBlurredThumbnail(string $sourcePath, string $outputPath, array $settings = []): void
    {
        $image = imagecreatefromwebp($sourcePath);
        if ($image === false) {
            return;
        }

        $width = imagesx($image);
        $height = imagesy($image);

        // デフォルト設定
        $blurStrength = $settings['blur_strength'] ?? 10;
        $brightness = $settings['brightness'] ?? -30;
        $quality = $settings['quality'] ?? 75;

        // 高速ぼかし処理（縮小→拡大方式）
        $blurStrength = max(1, min(20, $blurStrength));
        $smallWidth = max(1, (int)($width / $blurStrength));
        $smallHeight = max(1, (int)($height / $blurStrength));

        $smallImage = imagecreatetruecolor($smallWidth, $smallHeight);
        imagecopyresampled($smallImage, $image, 0, 0, 0, 0, $smallWidth, $smallHeight, $width, $height);
        imagecopyresampled($image, $smallImage, 0, 0, 0, 0, $width, $height, $smallWidth, $smallHeight);
        imagedestroy($smallImage);

        // 明度調整
        imagefilter($image, IMG_FILTER_BRIGHTNESS, $brightness);

        imagewebp($image, $outputPath, $quality);
        imagedestroy($image);
    }

    /**
     * すりガラス効果のサムネイルを生成（NSFW用）
     *
     * @param string $sourcePath 元サムネイルのパス
     * @param string $outputPath 出力パス
     * @param array $settings フィルター設定（blur_passes, contrast, brightness, overlay_opacity, quality）
     */
    private function createFrostedThumbnail(string $sourcePath, string $outputPath, array $settings = []): void
    {
        $image = imagecreatefromwebp($sourcePath);
        if ($image === false) {
            return;
        }

        $width = imagesx($image);
        $height = imagesy($image);

        // デフォルト設定
        $blurStrength = $settings['blur_strength'] ?? 10; // 1-20推奨、高いほどぼける
        $contrast = $settings['contrast'] ?? -10;
        $brightness = $settings['brightness'] ?? 15;
        $overlayOpacity = $settings['overlay_opacity'] ?? 30;
        $quality = $settings['quality'] ?? 80;

        // 1. 高速ぼかし処理（縮小→拡大方式）
        $blurStrength = max(1, min(20, $blurStrength)); // 1-20に制限
        $smallWidth = max(1, (int)($width / $blurStrength));
        $smallHeight = max(1, (int)($height / $blurStrength));

        // 一時的に小さい画像を作成
        $smallImage = imagecreatetruecolor($smallWidth, $smallHeight);
        imagecopyresampled($smallImage, $image, 0, 0, 0, 0, $smallWidth, $smallHeight, $width, $height);

        // 元のサイズに戻す（この過程でぼかしがかかる）
        imagecopyresampled($image, $smallImage, 0, 0, 0, 0, $width, $height, $smallWidth, $smallHeight);
        imagedestroy($smallImage);

        // 2. コントラスト調整（色を鮮やかに保つ）
        imagefilter($image, IMG_FILTER_CONTRAST, $contrast);

        // 3. 明度調整（透明感を出す）
        imagefilter($image, IMG_FILTER_BRIGHTNESS, $brightness);

        // 4. 半透明の白いオーバーレイを追加（すりガラス感を強調）
        // overlay_opacityは0-100のパーセント値として扱う（0=透明、100=完全不透明）
        $overlayOpacity = min(100, max(0, $overlayOpacity)); // 0-100に制限

        // ピクセル単位でアルファブレンディング
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

        imagewebp($image, $outputPath, $quality);
        imagedestroy($image);
    }

    /**
     * ユニークなファイル名を生成
     *
     * @param string $prefix プレフィックス（例: "bulk_", "post_"）
     * @return string 拡張子なしのファイル名
     */
    public function generateUniqueFilename(string $prefix = ''): string
    {
        return $prefix . date('Ymd_His') . '_' . bin2hex(random_bytes(8));
    }
}
