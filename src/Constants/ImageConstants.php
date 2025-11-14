<?php

declare(strict_types=1);

namespace App\Constants;

/**
 * 画像処理関連の定数
 */
class ImageConstants
{
    /**
     * サムネイル画質（WebP）
     */
    public const THUMBNAIL_QUALITY = 85;

    /**
     * フル画像品質（WebP）
     */
    public const FULL_IMAGE_QUALITY = 90;

    /**
     * サムネイルの最大幅
     */
    public const THUMBNAIL_MAX_WIDTH = 600;

    /**
     * サムネイルの最大高さ
     */
    public const THUMBNAIL_MAX_HEIGHT = 600;

    /**
     * ぼかし効果の適用回数
     */
    public const BLUR_PASSES = 20;

    /**
     * すりガラス効果の適用回数
     */
    public const FROSTED_BLUR_PASSES = 25;

    /**
     * NSFWフィルター: ぼかし
     */
    public const NSFW_FILTER_TYPE_BLUR = 'blur';

    /**
     * NSFWフィルター: すりガラス
     */
    public const NSFW_FILTER_TYPE_FROSTED = 'frosted';
}
