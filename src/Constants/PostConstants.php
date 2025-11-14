<?php

declare(strict_types=1);

namespace App\Constants;

/**
 * 投稿関連の定数
 */
class PostConstants
{
    /**
     * 1ページあたりの最大投稿数
     */
    public const MAX_POSTS_PER_PAGE = 50;

    /**
     * デフォルトの投稿数
     */
    public const DEFAULT_POSTS_PER_PAGE = 18;

    /**
     * 投稿タイプ: 単一画像
     */
    public const POST_TYPE_SINGLE = 0;

    /**
     * 投稿タイプ: グループ投稿
     */
    public const POST_TYPE_GROUP = 1;

    /**
     * NSFWフィルター: すべて表示
     */
    public const NSFW_FILTER_ALL = 'all';

    /**
     * NSFWフィルター: 一般のみ
     */
    public const NSFW_FILTER_SAFE = 'safe';

    /**
     * NSFWフィルター: NSFWのみ
     */
    public const NSFW_FILTER_NSFW = 'nsfw';
}
