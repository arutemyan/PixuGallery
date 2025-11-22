<?php

declare(strict_types=1);

namespace App\Models;

use App\Database\Connection;
use App\Services\PostTagService;
use PDO;

/**
 * タグモデルクラス
 *
 * タグデータのCRUD操作を管理
 */
class Tag
{
    private PDO $db;
    private ?PostTagService $tagService = null;

    public function __construct()
    {
        $this->db = Connection::getInstance();
    }

    /**
     * PostTagServiceのインスタンスを取得（遅延初期化）
     *
     * @return PostTagService
     */
    private function getTagService(): PostTagService
    {
        if ($this->tagService === null) {
            $this->tagService = new PostTagService($this->db);
        }
        return $this->tagService;
    }

    /**
     * すべてのタグを取得
     *
     * @param bool $includePostCount 投稿数を含めるか（デフォルト: false）
     * @return array タグデータの配列
     */
    public function getAll(bool $includePostCount = false): array
    {
        $stmt = $this->db->query("
            SELECT t.id, t.name, t.created_at
            FROM tags t
            ORDER BY t.name ASC
        ");
        $tags = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if ($includePostCount) {
            $tags = $this->addPostCountsToTags($tags);
        }

        return $tags;
    }

    /**
     * 人気のタグを取得（投稿数が多い順）
     * 表示中の投稿（is_visible=1）のみをカウント
     *
     * @param int $limit 取得件数（デフォルト: 10）
     * @return array タグデータの配列（post_count含む）
     */
    public function getPopular(int $limit = 10): array
    {
        // すべてのタグを取得
        $stmt = $this->db->query("SELECT id, name, created_at FROM tags");
        $allTags = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // 投稿数を追加
        $tagsWithCounts = $this->addPostCountsToTags($allTags);

        // 投稿数が1以上のタグのみフィルタリング
        $tagsWithCounts = array_filter($tagsWithCounts, function($tag) {
            return $tag['post_count'] > 0;
        });

        // 投稿数でソート（降順）、次にタグ名でソート（昇順）
        usort($tagsWithCounts, function($a, $b) {
            if ($a['post_count'] !== $b['post_count']) {
                return $b['post_count'] - $a['post_count'];
            }
            return strcmp($a['name'], $b['name']);
        });

        // 指定件数まで制限
        return array_slice($tagsWithCounts, 0, $limit);
    }

    /**
     * タグ名でタグを検索（部分一致）
     *
     * @param string $name 検索する名前
     * @param bool $includePostCount 投稿数を含めるか（デフォルト: true）
     * @return array タグデータの配列
     */
    public function searchByName(string $name, bool $includePostCount = true): array
    {
        // 入力検証: 空文字列または長すぎる検索クエリを拒否
        $trimmedName = trim($name);
        if (empty($trimmedName) || mb_strlen($trimmedName) > 100) {
            return [];
        }
        // 禁止文字のチェック: '%'、'_'、'\\' を検索語に含めると挙動が複雑になるため拒否する
        // （テストや既存のワイルドカードエスケープ処理を簡素化するための設計選択）
        if (preg_match('/[%_\\\\]/u', $trimmedName)) {
            return [];
        }
        
        // プレースホルダに検索パターンを渡す（禁止文字は上ですでにチェック済み）
        $pattern = '%' . $trimmedName . '%';
        // Choose DB-specific case-insensitive comparison to avoid applying
        // a function to the column where possible (helps index usage).
        $driver = \App\Database\DatabaseHelper::getDriver($this->db);
        if ($driver === 'sqlite') {
            // SQLite: use COLLATE NOCASE
            $sql = "SELECT t.id, t.name, t.created_at FROM tags t WHERE t.name LIKE ? COLLATE NOCASE ORDER BY t.name ASC";
        } elseif ($driver === 'postgresql') {
            // PostgreSQL: use ILIKE
            $sql = "SELECT t.id, t.name, t.created_at FROM tags t WHERE t.name ILIKE ? ORDER BY t.name ASC";
        } else {
            // MySQL and others: rely on column collation (most MySQL setups use ci collation)
            $sql = "SELECT t.id, t.name, t.created_at FROM tags t WHERE t.name LIKE ? ORDER BY t.name ASC";
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$pattern]);
        $tags = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if ($includePostCount) {
            $tags = $this->addPostCountsToTags($tags);
            // 投稿数でソート（降順）、次にタグ名でソート（昇順）
            usort($tags, function($a, $b) {
                if ($a['post_count'] !== $b['post_count']) {
                    return $b['post_count'] - $a['post_count'];
                }
                return strcmp($a['name'], $b['name']);
            });
        }

        return $tags;
    }

    /**
     * タグIDでタグを取得
     *
     * @param int $id タグID
     * @param bool $includePostCount 投稿数を含めるか（デフォルト: false）
     * @return array|null タグデータ、存在しない場合はnull
     */
    public function getById(int $id, bool $includePostCount = false): ?array
    {
        $stmt = $this->db->prepare("SELECT t.id, t.name, t.created_at FROM tags t WHERE t.id = ?");
        $stmt->execute([$id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($result === false) {
            return null;
        }

        if ($includePostCount) {
            $tags = $this->addPostCountsToTags([$result]);
            return $tags[0];
        }

        return $result;
    }

    /**
     * タグ名でタグを取得（完全一致）
     *
     * @param string $name タグ名
     * @return array|null タグデータ、存在しない場合はnull
     */
    public function getByName(string $name): ?array
    {
        $stmt = $this->db->prepare("
            SELECT t.id, t.name, t.created_at
            FROM tags t
            WHERE t.name = ?
        ");
        $stmt->execute([$name]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result !== false ? $result : null;
    }

    /**
     * 新しいタグを作成
     *
     * @param string $name タグ名
     * @return int 作成されたタグのID
     * @throws \PDOException タグが既に存在する場合
     */
    public function create(string $name): int
    {
        $stmt = $this->db->prepare("INSERT INTO tags (name) VALUES (?)");
        $stmt->execute([trim($name)]);

        return (int)$this->db->lastInsertId();
    }

    /**
     * タグを作成または取得（既に存在する場合は既存のIDを返す）
     *
     * @param string $name タグ名
     * @return int タグID
     */
    public function findOrCreate(string $name): int
    {
        $name = trim($name);

        // 既存のタグを検索
        $existing = $this->getByName($name);
        if ($existing) {
            return (int)$existing['id'];
        }

        // 存在しない場合は作成
        try {
            return $this->create($name);
        } catch (\PDOException $e) {
            // 競合が発生した場合（並行処理）、再度取得を試みる
            $existing = $this->getByName($name);
            if ($existing) {
                return (int)$existing['id'];
            }
            throw $e;
        }
    }

    /**
     * タグを削除
     *
     * @param int $id タグID
     * @return bool 成功した場合true
     */
    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare("DELETE FROM tags WHERE id = ?");
        return $stmt->execute([$id]);
    }

    /**
     * 使用されていないタグを削除
     *
     * @return int 削除されたタグ数
     */
    public function deleteUnused(): int
    {
        $stmt = $this->db->exec("
            DELETE FROM tags
            WHERE id NOT IN (
                SELECT DISTINCT tag_value FROM (
                    SELECT tag1 as tag_value FROM posts WHERE tag1 IS NOT NULL
                    UNION SELECT tag2 FROM posts WHERE tag2 IS NOT NULL
                    UNION SELECT tag3 FROM posts WHERE tag3 IS NOT NULL
                    UNION SELECT tag4 FROM posts WHERE tag4 IS NOT NULL
                    UNION SELECT tag5 FROM posts WHERE tag5 IS NOT NULL
                    UNION SELECT tag6 FROM posts WHERE tag6 IS NOT NULL
                    UNION SELECT tag7 FROM posts WHERE tag7 IS NOT NULL
                    UNION SELECT tag8 FROM posts WHERE tag8 IS NOT NULL
                    UNION SELECT tag9 FROM posts WHERE tag9 IS NOT NULL
                    UNION SELECT tag10 FROM posts WHERE tag10 IS NOT NULL
                )
            )
        ");
        return $stmt;
    }

    /**
     * タグ総数を取得
     *
     * @return int タグ数
     */
    public function count(): int
    {
        $stmt = $this->db->query("SELECT COUNT(*) as count FROM tags");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int)$result['count'];
    }

    /**
     * タグ配列に投稿数を追加
     *
     * @param array $tags タグデータの配列
     * @return array 投稿数が追加されたタグデータの配列
     */
    private function addPostCountsToTags(array $tags): array
    {
        if (empty($tags)) {
            return $tags;
        }

        $tagService = $this->getTagService();

        // タグIDを収集
        $tagIds = array_column($tags, 'id');

        // 投稿数を一括取得
        $counts = $tagService->getPostCountsForTags($tagIds);

        // 各タグに投稿数を追加
        foreach ($tags as &$tag) {
            $tag['post_count'] = $counts[$tag['id']] ?? 0;
        }

        return $tags;
    }
}
