<?php

declare(strict_types=1);

namespace App\Models;

use App\Database\Connection;
use App\Utils\ViewCounter;
use App\Utils\AccessLogger;
use PDO;

/**
 * 投稿モデルクラス
 *
 * 投稿データのCRUD操作を管理
 */
class Post
{
    private PDO $db;
    private ViewCounter $viewCounter;
    private ?AccessLogger $accessLogger;

    public function __construct()
    {
        $this->db = Connection::getInstance();
        $this->viewCounter = new ViewCounter();
        $this->accessLogger = AccessLogger::isEnabled() ? new AccessLogger() : null;
    }

    /**
     * すべての投稿を取得（最大50件、新しい順）
     *
     * @param int $limit 取得件数（デフォルト: 50）
     * @param string $nsfwFilter NSFWフィルタ（all: すべて, safe: 一般のみ, nsfw: NSFWのみ）
     * @param string|null $tagFilter タグフィルタ（タグ名）
     * @return array 投稿データの配列
     */
    public function getAll(int $limit = 18, string $nsfwFilter = 'all', ?string $tagFilter = null, int $offset = 0): array
    {
        // セキュリティ: 上限値を強制（DoS攻撃対策）
        $limit = min($limit, 50); // 絶対に50件以上は返さない
        $offset = max($offset, 0); // 負のオフセットは無効

        $sql = "
            SELECT id, title, tags, detail, image_path, thumb_path, is_sensitive, is_visible, created_at
            FROM posts
            WHERE is_visible = 1
        ";
        $params = [];

        // NSFWフィルタ
        if ($nsfwFilter === 'safe') {
            $sql .= " AND (is_sensitive = 0 OR is_sensitive IS NULL)";
        } elseif ($nsfwFilter === 'nsfw') {
            $sql .= " AND is_sensitive = 1";
        }

        // 以下の文字は解析しないで結果なしにする
        function checkNGTag($t) {
            return false
                || strpos($t, ";") !== false
                || strpos($t, '"') !== false
                || strpos($t, "'") !== false;
        }
        if (checkNGTag($tagFilter) || checkNGTag($nsfwFilter)) {
            return [];
        }

        // タグフィルタ
        if (!empty($tagFilter)) {
            //$sql .= " AND (tags LIKE ? OR tags LIKE ? OR tags LIKE ? OR tags = ?)";
            //$params[] = $tagFilter . ',%';  // 先頭
            //$params[] = '%,' . $tagFilter . ',%';  // 中間
            //$params[] = '%,' . $tagFilter;  // 末尾
            $sql .= " AND (tags = ?)";
            $params[] = $tagFilter;  // 単独
        }

        $sql .= " ORDER BY created_at DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $posts = $stmt->fetchAll();

        // 閲覧数を一括取得して追加
        if (!empty($posts)) {
            $postIds = array_column($posts, 'id');
            $viewCounts = $this->viewCounter->getBatch($postIds);

            foreach ($posts as &$post) {
                $post['view_count'] = $viewCounts[$post['id']] ?? 0;
            }
        }

        return $posts;
    }

    /**
     * 投稿IDで投稿を取得
     *
     * @param int $id 投稿ID
     * @return array|null 投稿データ、存在しない場合はnull
     */
    public function getById(int $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT id, title, tags, detail, image_path, thumb_path, is_sensitive, is_visible, created_at
            FROM posts
            WHERE id = ? AND is_visible = 1
        ");
        $stmt->execute([$id]);
        $result = $stmt->fetch();

        if ($result !== false) {
            // 閲覧数を追加
            $result['view_count'] = $this->viewCounter->get((int)$result['id']);
            return $result;
        }

        return null;
    }

    /**
     * 新しい投稿を作成
     *
     * @param string $title タイトル
     * @param string|null $tags タグ（カンマ区切り）
     * @param string|null $detail 詳細説明
     * @param string|null $imagePath 画像パス
     * @param string|null $thumbPath サムネイルパス
     * @param int $isSensitive センシティブ画像フラグ（0: 通常, 1: NSFW）
     * @return int 作成された投稿のID
     */
    public function create(
        string $title,
        ?string $tags = null,
        ?string $detail = null,
        ?string $imagePath = null,
        ?string $thumbPath = null,
        int $isSensitive = 0
    ): int {
        $stmt = $this->db->prepare("
            INSERT INTO posts (title, tags, detail, image_path, thumb_path, is_sensitive)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$title, $tags, $detail, $imagePath, $thumbPath, $isSensitive]);

        $postId = (int)$this->db->lastInsertId();

        // タグを関連付け
        if (!empty($tags)) {
            $tagArray = array_map('trim', explode(',', $tags));
            $this->attachTags($postId, $tagArray);
        }

        return $postId;
    }

    /**
     * 投稿を更新
     *
     * @param int $id 投稿ID
     * @param string $title タイトル
     * @param string|null $tags タグ
     * @param string|null $detail 詳細説明
     * @param string|null $imagePath 画像パス
     * @param string|null $thumbPath サムネイルパス
     * @return bool 成功した場合true
     */
    public function update(
        int $id,
        string $title,
        ?string $tags = null,
        ?string $detail = null,
        ?string $imagePath = null,
        ?string $thumbPath = null
    ): bool {
        $stmt = $this->db->prepare("
            UPDATE posts
            SET title = ?, tags = ?, detail = ?, image_path = ?, thumb_path = ?
            WHERE id = ?
        ");
        return $stmt->execute([$title, $tags, $detail, $imagePath, $thumbPath, $id]);
    }

    /**
     * 投稿のテキストフィールドのみを更新（画像は変更しない）
     *
     * @param int $id 投稿ID
     * @param string $title タイトル
     * @param string|null $tags タグ
     * @param string|null $detail 詳細説明
     * @param int $isSensitive センシティブ画像フラグ（0: 通常, 1: NSFW）
     * @return bool 成功した場合true
     */
    public function updateTextOnly(
        int $id,
        string $title,
        ?string $tags = null,
        ?string $detail = null,
        int $isSensitive = 0
    ): bool {
        $stmt = $this->db->prepare("
            UPDATE posts
            SET title = ?, tags = ?, detail = ?, is_sensitive = ?
            WHERE id = ?
        ");
        $result = $stmt->execute([$title, $tags, $detail, $isSensitive, $id]);

        // タグを更新
        if ($tags !== null) {
            $tagArray = array_map('trim', explode(',', $tags));
            $this->attachTags($id, $tagArray);
        }

        return $result;
    }

    /**
     * 投稿を削除
     *
     * @param int $id 投稿ID
     * @return bool 成功した場合true
     */
    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare("DELETE FROM posts WHERE id = ?");
        return $stmt->execute([$id]);
    }

    /**
     * 投稿数を取得
     *
     * @return int 投稿数
     */
    public function count(): int
    {
        $stmt = $this->db->query("SELECT COUNT(*) as count FROM posts");
        $result = $stmt->fetch();
        return (int)$result['count'];
    }

    /**
     * 閲覧回数をインクリメント
     *
     * @param int $id 投稿ID
     * @return bool 成功した場合true
     */
    public function incrementViewCount(int $id): bool
    {
        // カウンターDBで閲覧数をインクリメント
        $success = $this->viewCounter->increment($id);

        // アクセスログが有効な場合は記録
        if ($this->accessLogger !== null) {
            $this->accessLogger->log($id);
        }

        return $success;
    }

    /**
     * 管理画面用: 全投稿を取得（非表示含む）
     *
     * @param int $limit 取得件数
     * @param int $offset オフセット
     * @return array 投稿データの配列
     */
    public function getAllForAdmin(int $limit = 100, int $offset = 0): array
    {
        $limit = min($limit, 1000);
        $offset = max($offset, 0);

        $stmt = $this->db->prepare("
            SELECT id, title, tags, detail, image_path, thumb_path, is_sensitive, is_visible, created_at
            FROM posts
            ORDER BY created_at DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->execute([$limit, $offset]);
        $posts = $stmt->fetchAll();

        // 閲覧数を一括取得して追加
        if (!empty($posts)) {
            $postIds = array_column($posts, 'id');
            $viewCounts = $this->viewCounter->getBatch($postIds);

            foreach ($posts as &$post) {
                $post['view_count'] = $viewCounts[$post['id']] ?? 0;
            }
        }

        return $posts;
    }

    /**
     * 投稿の総件数を取得
     *
     * @return int 投稿の総件数
     */
    public function getTotalCount(): int
    {
        $stmt = $this->db->query("SELECT COUNT(*) as count FROM posts");
        $result = $stmt->fetch();
        return (int)($result['count'] ?? 0);
    }

    /**
     * 管理画面用: IDで投稿を取得（非表示含む）
     *
     * @param int $id 投稿ID
     * @return array|null 投稿データ、存在しない場合はnull
     */
    public function getByIdForAdmin(int $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT id, title, tags, detail, image_path, thumb_path, is_sensitive, is_visible, created_at
            FROM posts
            WHERE id = ?
        ");
        $stmt->execute([$id]);
        $result = $stmt->fetch();

        if ($result !== false) {
            $result['view_count'] = $this->viewCounter->get((int)$result['id']);
            return $result;
        }

        return null;
    }

    /**
     * 投稿の表示/非表示を切り替え
     *
     * @param int $id 投稿ID
     * @param int $isVisible 表示状態（1: 表示, 0: 非表示）
     * @return bool 成功した場合true
     */
    public function setVisibility(int $id, int $isVisible): bool
    {
        $stmt = $this->db->prepare("
            UPDATE posts
            SET is_visible = ?
            WHERE id = ?
        ");
        return $stmt->execute([$isVisible, $id]);
    }

    /**
     * 一括アップロード用: 画像を非表示状態で登録
     *
     * @param string $imagePath 画像パス
     * @param string|null $thumbPath サムネイルパス
     * @return int 作成された投稿のID
     */
    public function createBulk(string $imagePath, ?string $thumbPath = null): int
    {
        // ファイル名から簡易的なタイトルを生成
        $filename = basename($imagePath);
        $title = pathinfo($filename, PATHINFO_FILENAME);

        $stmt = $this->db->prepare("
            INSERT INTO posts (title, image_path, thumb_path, is_visible, is_sensitive)
            VALUES (?, ?, ?, 0, 0)
        ");
        $stmt->execute([$title, $imagePath, $thumbPath]);

        return (int)$this->db->lastInsertId();
    }

    /**
     * 閲覧回数を取得
     *
     * @param int $id 投稿ID
     * @return int 閲覧回数
     */
    public function getViewCount(int $id): int
    {
        return $this->viewCounter->get($id);
    }

    /**
     * タグで投稿を検索
     *
     * @param string $tagName タグ名
     * @param int $limit 取得件数（デフォルト: 50）
     * @return array 投稿データの配列
     */
    public function getByTag(string $tagName, int $limit = 50): array
    {
        $stmt = $this->db->prepare("
            SELECT p.id, p.title, p.tags, p.detail, p.image_path, p.thumb_path, p.is_sensitive, p.created_at,
                   GROUP_CONCAT(t.name, ',') as all_tags
            FROM posts p
            INNER JOIN post_tags pt ON p.id = pt.post_id
            INNER JOIN tags t ON pt.tag_id = t.id
            WHERE p.id IN (
                SELECT pt2.post_id
                FROM post_tags pt2
                INNER JOIN tags t2 ON pt2.tag_id = t2.id
                WHERE t2.name = ?
            )
            GROUP BY p.id
            ORDER BY p.created_at DESC
            LIMIT ?
        ");
        $stmt->execute([$tagName, $limit]);
        $results = $stmt->fetchAll();

        // all_tagsをtagsフィールドにマップ（後方互換性のため）
        foreach ($results as &$result) {
            if (isset($result['all_tags'])) {
                $result['tags'] = $result['all_tags'];
                unset($result['all_tags']);
            }
        }

        // 閲覧数を一括取得して追加
        if (!empty($results)) {
            $postIds = array_column($results, 'id');
            $viewCounts = $this->viewCounter->getBatch($postIds);

            foreach ($results as &$result) {
                $result['view_count'] = $viewCounts[$result['id']] ?? 0;
            }
        }

        return $results;
    }

    /**
     * 複数のタグで投稿を検索（AND検索）
     *
     * @param array $tagNames タグ名の配列
     * @param int $limit 取得件数（デフォルト: 50）
     * @return array 投稿データの配列
     */
    public function getByTags(array $tagNames, int $limit = 50): array
    {
        if (empty($tagNames)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($tagNames), '?'));

        $stmt = $this->db->prepare("
            SELECT p.id, p.title, p.tags, p.detail, p.image_path, p.thumb_path, p.is_sensitive, p.created_at,
                   GROUP_CONCAT(t.name, ',') as all_tags
            FROM posts p
            INNER JOIN post_tags pt ON p.id = pt.post_id
            INNER JOIN tags t ON pt.tag_id = t.id
            WHERE p.id IN (
                SELECT pt2.post_id
                FROM post_tags pt2
                INNER JOIN tags t2 ON pt2.tag_id = t2.id
                WHERE t2.name IN ({$placeholders})
                GROUP BY pt2.post_id
                HAVING COUNT(DISTINCT t2.id) = ?
            )
            GROUP BY p.id
            ORDER BY p.created_at DESC
            LIMIT ?
        ");

        $params = array_merge($tagNames, [count($tagNames), $limit]);
        $stmt->execute($params);
        $results = $stmt->fetchAll();

        // all_tagsをtagsフィールドにマップ
        foreach ($results as &$result) {
            if (isset($result['all_tags'])) {
                $result['tags'] = $result['all_tags'];
                unset($result['all_tags']);
            }
        }

        // 閲覧数を一括取得して追加
        if (!empty($results)) {
            $postIds = array_column($results, 'id');
            $viewCounts = $this->viewCounter->getBatch($postIds);

            foreach ($results as &$result) {
                $result['view_count'] = $viewCounts[$result['id']] ?? 0;
            }
        }

        return $results;
    }

    /**
     * 投稿にタグを関連付け
     *
     * @param int $postId 投稿ID
     * @param array $tagNames タグ名の配列
     * @return void
     */
    public function attachTags(int $postId, array $tagNames): void
    {
        // 既存のタグを削除
        $stmt = $this->db->prepare("DELETE FROM post_tags WHERE post_id = ?");
        $stmt->execute([$postId]);

        // posts.tagsカラムも更新（後方互換性のため）
        $tagsString = implode(',', array_map('trim', $tagNames));
        $stmt = $this->db->prepare("UPDATE posts SET tags = ? WHERE id = ?");
        $stmt->execute([$tagsString, $postId]);

        // 新しいタグを追加
        foreach ($tagNames as $tagName) {
            $tagName = trim($tagName);
            if (empty($tagName)) {
                continue;
            }

            // タグを取得または作成
            $stmt = $this->db->prepare("INSERT OR IGNORE INTO tags (name) VALUES (?)");
            $stmt->execute([$tagName]);

            $stmt = $this->db->prepare("SELECT id FROM tags WHERE name = ?");
            $stmt->execute([$tagName]);
            $tag = $stmt->fetch();

            if ($tag) {
                // 関連付け
                $stmt = $this->db->prepare("INSERT OR IGNORE INTO post_tags (post_id, tag_id) VALUES (?, ?)");
                $stmt->execute([$postId, $tag['id']]);
            }
        }
    }

    /**
     * 投稿のタグを取得
     *
     * @param int $postId 投稿ID
     * @return array タグ名の配列
     */
    public function getTags(int $postId): array
    {
        $stmt = $this->db->prepare("
            SELECT t.name
            FROM tags t
            INNER JOIN post_tags pt ON t.id = pt.tag_id
            WHERE pt.post_id = ?
            ORDER BY t.name ASC
        ");
        $stmt->execute([$postId]);
        $results = $stmt->fetchAll();

        return array_column($results, 'name');
    }
}
