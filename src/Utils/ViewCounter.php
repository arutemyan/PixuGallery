<?php

declare(strict_types=1);

namespace App\Utils;

use App\Database\CountersConnection;
use App\Utils\Logger;
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
     * （旧）閲覧数インクリメントの説明は削除 - 新しい実装は下にあります
     */
        /**
         * 閲覧数をインクリメント（重複visitorの短時間カウント抑制をDB側で行う）
         *
         * @param int $postId
         * @param int $postType
         * @param string|null $visitorHash クライアント識別子のハッシュ（NULLなら重複判定を行わない）
         * @param int $windowSeconds 同一訪問とみなすウィンドウ（秒）
         * @return bool 成功したかどうか（DBエラー時はfalse）
         */
        public function increment(int $postId, int $postType = 0, ?string $visitorHash = null, int $windowSeconds = 60): bool
        {
            try {
                $now = time();

                // トランザクション開始
                $this->db->beginTransaction();

                $stmt = $this->db->prepare("SELECT count, last_visitor_hash, last_viewed_at FROM view_counts WHERE post_id = ? AND post_type = ?");
                $stmt->execute([$postId, $postType]);
                $row = $stmt->fetch();

                $cutoff = $now - max(0, $windowSeconds);
                $allowIncrement = true;

                if ($row) {
                    $lastVisitor = $row['last_visitor_hash'] ?? null;
                    $lastTs = isset($row['last_viewed_at']) ? (int)$row['last_viewed_at'] : 0;

                    if (!empty($visitorHash) && $lastVisitor !== null && hash_equals((string)$lastVisitor, (string)$visitorHash) && $lastTs > $cutoff) {
                        // 同一 visitor の短時間内重複 -> インクリメントしない
                        $allowIncrement = false;
                    }
                }

                if ($allowIncrement) {
                    if ($row) {
                        // 更新
                        $update = $this->db->prepare("UPDATE view_counts SET count = count + 1, last_visitor_hash = ?, last_viewed_at = ?, updated_at = CURRENT_TIMESTAMP WHERE post_id = ? AND post_type = ?");
                        $update->execute([$visitorHash, $now, $postId, $postType]);
                    } else {
                        // 挿入
                        $insert = $this->db->prepare("INSERT INTO view_counts (post_id, post_type, count, last_visitor_hash, last_viewed_at, updated_at) VALUES (?, ?, 1, ?, ?, CURRENT_TIMESTAMP)");
                        $insert->execute([$postId, $postType, $visitorHash, $now]);
                    }
                }

                $this->db->commit();
                return true;
            } catch (\Exception $e) {
                // トランザクションをロールバック
                try {
                    if ($this->db->inTransaction()) {
                        $this->db->rollBack();
                    }
                } catch (\Throwable $t) {
                    // ignore
                }

                Logger::getInstance()->error("ViewCounter::increment error: " . $e->getMessage());
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
            Logger::getInstance()->error("ViewCounter::get error: " . $e->getMessage());
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
            Logger::getInstance()->error("ViewCounter::getBatch error: " . $e->getMessage());
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
            Logger::getInstance()->error("ViewCounter::reset error: " . $e->getMessage());
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
            Logger::getInstance()->error("ViewCounter::getPopularPostIds error: " . $e->getMessage());
            return [];
        }
    }
}
