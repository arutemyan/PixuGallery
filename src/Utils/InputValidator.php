<?php

declare(strict_types=1);

namespace App\Utils;

/**
 * 入力検証ユーティリティクラス
 *
 * アプリケーション全体で統一された入力検証を提供
 */
class InputValidator
{
    /**
     * SQLインジェクションパターンをチェック（レガシー互換用）
     *
     * 注意: プリペアドステートメント使用時は基本的に不要
     * この関数は後方互換性のために残していますが、
     * プリペアドステートメントを使用することを推奨します
     *
     * @param string $input 検証する文字列
     * @return bool インジェクションパターンが含まれている場合true
     */
    public static function hasInjectionPattern(string $input): bool
    {
        return str_contains($input, ';')
            || str_contains($input, '"')
            || str_contains($input, "'");
    }

    /**
     * タグIDを検証
     *
     * @param int|null $tagId タグID
     * @return bool 有効な場合true
     */
    public static function validateTagId(?int $tagId): bool
    {
        if ($tagId === null) {
            return true;
        }
        return $tagId > 0 && $tagId < PHP_INT_MAX;
    }

    /**
     * NSFWフィルターを検証
     *
     * @param string $filter フィルター値
     * @return bool 有効な場合true
     */
    public static function validateNsfwFilter(string $filter): bool
    {
        return in_array($filter, ['all', 'safe', 'nsfw'], true);
    }

    /**
     * ページネーションのlimitを検証・正規化
     *
     * @param int $limit 要求されたlimit値
     * @param int $max 最大値（デフォルト: 100）
     * @param int $default デフォルト値（デフォルト: 20）
     * @return int 正規化されたlimit値
     */
    public static function normalizeLimit(int $limit, int $max = 100, int $default = 20): int
    {
        if ($limit < 1) {
            return $default;
        }
        return min($limit, $max);
    }

    /**
     * ページネーションのoffsetを検証・正規化
     *
     * @param int $offset 要求されたoffset値
     * @return int 正規化されたoffset値（負の値の場合は0）
     */
    public static function normalizeOffset(int $offset): int
    {
        return max(0, $offset);
    }

    /**
     * ファイルパスがベースディレクトリ内にあるか検証
     *
     * パストラバーサル攻撃を防ぐ
     *
     * @param string $path 検証するパス
     * @param string $baseDir ベースディレクトリ
     * @return bool ベースディレクトリ内にある場合true
     */
    public static function isPathInDirectory(string $path, string $baseDir): bool
    {
        $realPath = realpath($path);
        $realBase = realpath($baseDir);

        if ($realPath === false || $realBase === false) {
            return false;
        }

        return strpos($realPath, $realBase) === 0;
    }

    /**
     * 数値IDを検証
     *
     * @param mixed $id 検証する値
     * @return bool 正の整数の場合true
     */
    public static function validatePositiveId(mixed $id): bool
    {
        if (!is_numeric($id)) {
            return false;
        }
        $intId = (int)$id;
        return $intId > 0;
    }

    /**
     * 文字列の長さを検証
     *
     * @param string $str 検証する文字列
     * @param int $min 最小長（デフォルト: 0）
     * @param int $max 最大長（デフォルト: PHP_INT_MAX）
     * @return bool 有効な長さの場合true
     */
    public static function validateLength(string $str, int $min = 0, int $max = PHP_INT_MAX): bool
    {
        $length = mb_strlen($str);
        return $length >= $min && $length <= $max;
    }

    /**
     * メールアドレス形式を検証
     *
     * @param string $email メールアドレス
     * @return bool 有効なメールアドレスの場合true
     */
    public static function validateEmail(string $email): bool
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * URL形式を検証
     *
     * @param string $url URL
     * @return bool 有効なURLの場合true
     */
    public static function validateUrl(string $url): bool
    {
        return filter_var($url, FILTER_VALIDATE_URL) !== false;
    }

    /**
     * 英数字のみかを検証
     *
     * @param string $str 検証する文字列
     * @return bool 英数字のみの場合true
     */
    public static function isAlphanumeric(string $str): bool
    {
        return preg_match('/^[a-zA-Z0-9]+$/', $str) === 1;
    }

    /**
     * 英数字とアンダースコアのみかを検証
     *
     * @param string $str 検証する文字列
     * @return bool 英数字とアンダースコアのみの場合true
     */
    public static function isAlphanumericWithUnderscore(string $str): bool
    {
        return preg_match('/^[a-zA-Z0-9_]+$/', $str) === 1;
    }
}
