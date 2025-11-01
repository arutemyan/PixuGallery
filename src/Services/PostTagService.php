<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Tag;
use PDO;

/**
 * 投稿とタグの関連を管理するサービスクラス
 *
 * 責務:
 * - タグ文字列 ⟷ タグID配列の変換
 * - tag1～tag10カラムの操作ロジック
 * - タグからの投稿検索条件生成
 */
class PostTagService
{
    private PDO $db;
    private Tag $tagModel;

    public function __construct(PDO $db)
    {
        $this->db = $db;
        $this->tagModel = new Tag();
    }

    /**
     * タグ文字列（カンマ区切り）をタグID配列に変換
     *
     * @param string|null $tags タグ文字列（カンマ区切り）
     * @return array 10要素の配列（tag1～tag10のタグID、または null）
     */
    public function parseTagsToIds(?string $tags): array
    {
        $tagNames = $this->parseTagString($tags);
        return $this->getOrCreateTagIds($tagNames);
    }

    /**
     * タグID配列（tag1～tag10）をタグ名のカンマ区切り文字列に変換
     *
     * @param array $tagIds tag1～tag10のタグID配列
     * @return string カンマ区切りのタグ名文字列
     */
    public function convertIdsToNames(array $tagIds): string
    {
        // nullや空の要素を除外
        $validTagIds = array_filter($tagIds, function($id) {
            return $id !== null && $id > 0;
        });

        if (empty($validTagIds)) {
            return '';
        }

        // タグIDからタグ名を一括取得
        $placeholders = implode(',', array_fill(0, count($validTagIds), '?'));
        $stmt = $this->db->prepare("SELECT name FROM tags WHERE id IN ({$placeholders}) ORDER BY id");
        $stmt->execute(array_values($validTagIds));
        $tags = $stmt->fetchAll(PDO::FETCH_COLUMN);

        return implode(',', $tags);
    }

    /**
     * 投稿行データ（tag1～tag10カラム含む）からタグ名文字列を取得
     *
     * @param array $row 投稿行データ
     * @return string カンマ区切りのタグ名文字列
     */
    public function getTagsFromRow(array $row): string
    {
        $tagIds = [];

        // tag1～tag10からタグIDを取得
        for ($i = 1; $i <= 10; $i++) {
            $tagKey = "tag{$i}";
            if (isset($row[$tagKey]) && !empty($row[$tagKey])) {
                $tagIds[] = (int)$row[$tagKey];
            }
        }

        return $this->convertIdsToNames($tagIds);
    }

    /**
     * タグによる検索条件（WHERE句）を生成
     *
     * @param int $tagId タグID
     * @return array ['sql' => SQL条件, 'params' => パラメータ配列]
     */
    public function buildTagSearchCondition(int $tagId): array
    {
        $sql = "(tag1 = ? OR tag2 = ? OR tag3 = ? OR tag4 = ? OR tag5 = ? OR tag6 = ? OR tag7 = ? OR tag8 = ? OR tag9 = ? OR tag10 = ?)";
        $params = array_fill(0, 10, $tagId);

        return ['sql' => $sql, 'params' => $params];
    }

    /**
     * 複数タグによるAND検索条件を生成
     *
     * @param array $tagIds タグIDの配列
     * @return array ['sql' => SQL条件, 'params' => パラメータ配列]
     */
    public function buildMultiTagSearchCondition(array $tagIds): array
    {
        if (empty($tagIds)) {
            return ['sql' => '1=1', 'params' => []];
        }

        $conditions = [];
        $params = [];

        foreach ($tagIds as $tagId) {
            $conditions[] = "(tag1 = ? OR tag2 = ? OR tag3 = ? OR tag4 = ? OR tag5 = ? OR tag6 = ? OR tag7 = ? OR tag8 = ? OR tag9 = ? OR tag10 = ?)";
            for ($i = 0; $i < 10; $i++) {
                $params[] = $tagId;
            }
        }

        $sql = implode(' AND ', $conditions);

        return ['sql' => $sql, 'params' => $params];
    }

    /**
     * タグ名配列をタグID配列に変換（タグが存在しない場合は作成）
     *
     * @param array $tagNames タグ名配列
     * @return array タグIDの配列
     */
    public function resolveTagNamesToIds(array $tagNames): array
    {
        $tagIds = [];

        foreach ($tagNames as $tagName) {
            if (empty($tagName)) {
                continue;
            }

            $tagId = $this->tagModel->findOrCreate($tagName);
            $tagIds[] = $tagId;
        }

        return $tagIds;
    }

    /**
     * タグの投稿数を取得（表示中の投稿のみ）
     *
     * @param int $tagId タグID
     * @param bool $visibleOnly 表示中の投稿のみカウントするか（デフォルト: true）
     * @return int 投稿数
     */
    public function getPostCountForTag(int $tagId, bool $visibleOnly = true): int
    {
        $visibilityCondition = $visibleOnly ? 'AND p.is_visible = 1' : '';

        $stmt = $this->db->prepare("
            SELECT COUNT(DISTINCT p.id) as count
            FROM posts p
            WHERE (p.tag1 = ? OR p.tag2 = ? OR p.tag3 = ? OR p.tag4 = ? OR p.tag5 = ?
                   OR p.tag6 = ? OR p.tag7 = ? OR p.tag8 = ? OR p.tag9 = ? OR p.tag10 = ?)
            {$visibilityCondition}
        ");
        $params = array_fill(0, 10, $tagId);
        $stmt->execute($params);
        $result = $stmt->fetch();

        return (int)$result['count'];
    }

    /**
     * 複数タグの投稿数を一括取得（表示中の投稿のみ）
     *
     * @param array $tagIds タグIDの配列
     * @param bool $visibleOnly 表示中の投稿のみカウントするか（デフォルト: true）
     * @return array タグIDをキーとした投稿数の連想配列
     */
    public function getPostCountsForTags(array $tagIds, bool $visibleOnly = true): array
    {
        if (empty($tagIds)) {
            return [];
        }

        $counts = [];
        foreach ($tagIds as $tagId) {
            $counts[$tagId] = $this->getPostCountForTag($tagId, $visibleOnly);
        }

        return $counts;
    }

    /**
     * タグ文字列をパースして配列に変換
     *
     * @param string|null $tags タグ文字列（カンマ区切り）
     * @return array タグ配列（最大10個）
     */
    private function parseTagString(?string $tags): array
    {
        if (empty($tags)) {
            return [];
        }

        // カンマで分割し、前後のスペース/タブを除去
        $tagArray = array_map('trim', explode(',', $tags));

        // 空要素を削除
        $tagArray = array_filter($tagArray, function($tag) {
            return !empty($tag);
        });

        // 最大10個に制限
        return array_slice($tagArray, 0, 10);
    }

    /**
     * タグ名配列からタグIDを取得または作成し、10要素配列に変換
     *
     * @param array $tagNames タグ名配列
     * @return array 10要素の配列（tag1～tag10のタグID、または null）
     */
    private function getOrCreateTagIds(array $tagNames): array
    {
        $tagIds = array_fill(0, 10, null);

        for ($i = 0; $i < min(count($tagNames), 10); $i++) {
            $tagName = $tagNames[$i];
            if (empty($tagName)) {
                continue;
            }

            // タグを取得または作成
            $tagId = $this->tagModel->findOrCreate($tagName);
            $tagIds[$i] = $tagId;
        }

        return $tagIds;
    }
}
