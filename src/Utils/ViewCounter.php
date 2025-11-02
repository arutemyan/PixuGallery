<?php

declare(strict_types=1);

namespace App\Utils;

use App\Database\CountersConnection;
use PDO;

/**
 * 閲覧数カウンタークラス
 *
 * 投稿の閲覧数をcounters.dbで管理
 */
class ViewCounter
{
    private PDO $db;

    public function __construct()
    {
        $this->db = CountersConnection::getInstance();
    }

    /**
     * 閲覧数をインクリメント
     *
     * @param int $postId 投稿ID
     * @param int $postType 投稿タイプ（0=single, 1=group）
     * @return bool 成功したかどうか
     */
    public function increment(int $postId, int $postType = 0): bool
    {
        try {
            // MySQL/PostgreSQLでは単純にVALUESを更新するが、SQLiteではcount+1にする必要がある
            $helper = \App\Database\DatabaseHelper::class;
            $driver = $helper::getDriver($this->db);
            if ($driver === 'mysql') {
                // MySQLの場合はcount + 1を手動で実装
                $stmt = $this->db->prepare("
                    INSERT INTO view_counts (post_id, post_type, count, updated_at)
                    VALUES (?, ?, 1, CURRENT_TIMESTAMP)
                    ON DUPLICATE KEY UPDATE
                        count = count + 1,
                        updated_at = CURRENT_TIMESTAMP
                ");
            } else {
                // SQLite/PostgreSQLの場合
                $stmt = $this->db->prepare("
                    INSERT INTO view_counts (post_id, post_type, count, updated_at)
                    VALUES (?, ?, 1, CURRENT_TIMESTAMP)
                    ON CONFLICT(post_id, post_type) DO UPDATE SET
                        count = count + 1,
                        updated_at = CURRENT_TIMESTAMP
                ");
            }

            $stmt->execute([$postId, $postType]);
            return true;
        } catch (\Exception $e) {
            error_log("ViewCounter::increment error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * 閲覧数を取得
     *
     * @param int $postId 投稿ID
     * @param int $postType 投稿タイプ（0=single, 1=group）
     * @return int 閲覧数
     */
    public function get(int $postId, int $postType = 0): int
    {
        try {
            $stmt = $this->db->prepare("SELECT count FROM view_counts WHERE post_id = ? AND post_type = ?");
            $stmt->execute([$postId, $postType]);
            $result = $stmt->fetch();

            return $result ? (int)$result['count'] : 0;
        } catch (\Exception $e) {
            error_log("ViewCounter::get error: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * 複数の投稿の閲覧数を一括取得
     *
     * @param array $postIds 投稿IDの配列
     * @param int $postType 投稿タイプ（0=single, 1=group）
     * @return array post_id => count の連想配列
     */
    public function getBatch(array $postIds, int $postType = 0): array
    {
        if (empty($postIds)) {
            return [];
        }

        try {
            $placeholders = implode(',', array_fill(0, count($postIds), '?'));
            $stmt = $this->db->prepare("
                SELECT post_id, count
                FROM view_counts
                WHERE post_id IN ($placeholders) AND post_type = ?
            ");
            $params = array_merge($postIds, [$postType]);
            $stmt->execute($params);
            $results = $stmt->fetchAll();

            $counts = [];
            foreach ($results as $row) {
                $counts[(int)$row['post_id']] = (int)$row['count'];
            }

            // 存在しないIDには0を設定
            foreach ($postIds as $postId) {
                if (!isset($counts[$postId])) {
                    $counts[$postId] = 0;
                }
            }

            return $counts;
        } catch (\Exception $e) {
            error_log("ViewCounter::getBatch error: " . $e->getMessage());
            return array_fill_keys($postIds, 0);
        }
    }

    /**
     * 閲覧数をリセット
     *
     * @param int $postId 投稿ID
     * @param int $postType 投稿タイプ（0=single, 1=group）
     * @return bool 成功したかどうか
     */
    public function reset(int $postId, int $postType = 0): bool
    {
        try {
            $stmt = $this->db->prepare("DELETE FROM view_counts WHERE post_id = ? AND post_type = ?");
            $stmt->execute([$postId, $postType]);
            return true;
        } catch (\Exception $e) {
            error_log("ViewCounter::reset error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * 人気の投稿を取得（閲覧数の多い順）
     *
     * @param int $limit 取得件数
     * @param int $postType 投稿タイプ（0=single, 1=group）
     * @return array post_idの配列
     */
    public function getPopularPostIds(int $limit = 10, int $postType = 0): array
    {
        try {
            $stmt = $this->db->prepare("
                SELECT post_id
                FROM view_counts
                WHERE post_type = ?
                ORDER BY count DESC
                LIMIT ?
            ");
            $stmt->execute([$postType, $limit]);
            $results = $stmt->fetchAll();

            return array_map(fn($row) => (int)$row['post_id'], $results);
        } catch (\Exception $e) {
            error_log("ViewCounter::getPopularPostIds error: " . $e->getMessage());
            return [];
        }
    }
}
