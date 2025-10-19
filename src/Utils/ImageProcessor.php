<?php

declare(strict_types=1);

namespace App\Utils;

use GdImage;

/**
 * 画像処理ユーティリティクラス
 */
class ImageProcessor
{
    private array $config;

    public function __construct()
    {
        $configPath = __DIR__ . '/../../config/config.php';
        $this->config = require $configPath;
    }

    public function createThumbnail(
        string $sourcePath,
        string $outputPath,
        ?int $width = null,
        ?int $height = null
    ): bool {
        if (!file_exists($sourcePath)) {
            return false;
        }

        $width = $width ?? $this->config['thumbnail']['width'];
        $height = $height ?? $this->config['thumbnail']['height'];
        $quality = $this->config['thumbnail']['quality'];

        $source = $this->loadImage($sourcePath);
        if ($source === null) {
            return false;
        }

        $srcWidth = imagesx($source);
        $srcHeight = imagesy($source);

        $ratio = min($width / $srcWidth, $height / $srcHeight);
        $newWidth = (int)($srcWidth * $ratio);
        $newHeight = (int)($srcHeight * $ratio);

        $thumb = imagecreatetruecolor($newWidth, $newHeight);
        imagealphablending($thumb, false);
        imagesavealpha($thumb, true);

        imagecopyresampled(
            $thumb,
            $source,
            0,
            0,
            0,
            0,
            $newWidth,
            $newHeight,
            $srcWidth,
            $srcHeight
        );

        $result = imagewebp($thumb, $outputPath, $quality);

        imagedestroy($source);
        imagedestroy($thumb);

        return $result;
    }

    public function createBlurredThumbnail(string $sourcePath, string $outputPath): bool
    {
        if (!file_exists($sourcePath)) {
            return false;
        }

        $source = $this->loadImage($sourcePath);
        if ($source === null) {
            return false;
        }

        // NSFW設定を読み込み
        $nsfwConfigPath = __DIR__ . '/../../config/config.php';
	$nsfwConfig = require $nsfwConfigPath;
	$nsfwConfig = $nsfwConfig['nsfw'];
        $frostedSettings = $nsfwConfig['frosted_settings'];

        $blurPasses = $frostedSettings['blur_passes'] ?? 3;
        $whiteOverlay = $frostedSettings['white_overlay'] ?? 80;
        $quality = $frostedSettings['quality'] ?? 85;

        // ガウシアンブラー処理
        $processed = $this->applyFastBlur($source, 0, $blurPasses);

        // ホワイトオーバーレイを適用してボケ感を強化
        $this->applyWhiteOverlay($processed, $whiteOverlay);

        $result = imagewebp($processed, $outputPath, $quality);

        imagedestroy($source);
        imagedestroy($processed);

        return $result;
    }

    /**
     * ガウシアンブラー処理
     *
     * @param GdImage $image 元画像
     * @param int $pixelSize 未使用（後方互換性のため保持）
     * @param int $blurPasses ガウシアンブラーの適用回数
     * @return GdImage 処理済み画像
     */
    private function applyFastBlur(GdImage $image, int $pixelSize, int $blurPasses): GdImage
    {
        $width = imagesx($image);
        $height = imagesy($image);

        // 画像を複製
        $blurred = imagecreatetruecolor($width, $height);
        imagealphablending($blurred, false);
        imagesavealpha($blurred, true);
        imagecopy($blurred, $image, 0, 0, 0, 0, $width, $height);

        // ガウシアンブラーを複数回適用
        for ($i = 0; $i < $blurPasses; $i++) {
            imagefilter($blurred, IMG_FILTER_GAUSSIAN_BLUR);
        }

        return $blurred;
    }

    /**
     * RGB値にホワイトを加算してボケ感を強化
     *
     * @param GdImage $image 処理対象の画像
     * @param int $whiteAmount ホワイト加算量（0-255）
     */
    private function applyWhiteOverlay(GdImage $image, int $whiteAmount): void
    {
        // 加算量を0-255の範囲に制限
        $whiteAmount = max(0, min(255, $whiteAmount));

        if ($whiteAmount === 0) {
            return;
        }

        $width = imagesx($image);
        $height = imagesy($image);

        // 全ピクセルに対してホワイトを加算
        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                $rgb = imagecolorat($image, $x, $y);

                // RGBとアルファ値を抽出
                $r = ($rgb >> 16) & 0xFF;
                $g = ($rgb >> 8) & 0xFF;
                $b = $rgb & 0xFF;
                $alpha = ($rgb >> 24) & 0x7F;

                // ホワイトを加算（255を超えないように制限）
                $r = min(255, $r + $whiteAmount);
                $g = min(255, $g + $whiteAmount);
                $b = min(255, $b + $whiteAmount);

                // 新しい色を設定
                $newColor = imagecolorallocatealpha($image, $r, $g, $b, $alpha);
                imagesetpixel($image, $x, $y, $newColor);
            }
        }
    }

    private function loadImage(string $path): ?GdImage
    {
        $info = getimagesize($path);
        if ($info === false) {
            return null;
        }

        $mimeType = $info['mime'];

        return match ($mimeType) {
            'image/jpeg' => imagecreatefromjpeg($path),
            'image/png' => imagecreatefrompng($path),
            'image/webp' => imagecreatefromwebp($path),
            'image/gif' => imagecreatefromgif($path),
            default => null,
        };
    }

    public function getConfig(): array
    {
        return $this->config;
    }
}
