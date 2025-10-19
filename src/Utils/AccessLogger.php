<?php

declare(strict_types=1);

namespace App\Utils;

use App\Database\AccessLogsConnection;
use PDO;

/**
 * アクセスログ記録クラス
 *
 * オプション機能として詳細なアクセスログを記録
 * 設定でON/OFFを切り替え可能
 */
class AccessLogger
{
    private ?PDO $db;

    public function __construct()
    {
        // アクセスログが有効な場合のみDB接続を取得
        $this->db = AccessLogsConnection::getInstance();
    }

    /**
     * アクセスログが有効かチェック
     *
     * @return bool
     */
    public static function isEnabled(): bool
    {
        return AccessLogsConnection::isEnabled();
    }

    /**
     * アクセスログを記録
     *
     * @param int $postId 投稿ID
     * @param array|null $serverData $_SERVER配列（テスト用にオーバーライド可能）
     * @return bool 成功したかどうか
     */
    public function log(int $postId, ?array $serverData = null): bool
    {
        // アクセスログが無効の場合は何もしない
        if ($this->db === null) {
            return false;
        }

        try {
            $serverData = $serverData ?? $_SERVER;

            $ipAddress = $this->getClientIp($serverData);
            $userAgent = $serverData['HTTP_USER_AGENT'] ?? '';
            $referer = $serverData['HTTP_REFERER'] ?? '';

            $stmt = $this->db->prepare("
                INSERT INTO access_logs (post_id, ip_address, user_agent, referer, accessed_at)
                VALUES (:post_id, :ip_address, :user_agent, :referer, CURRENT_TIMESTAMP)
            ");

            $stmt->execute([
                'post_id' => $postId,
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
                'referer' => $referer,
            ]);

            return true;
        } catch (\Exception $e) {
            error_log("AccessLogger::log error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * クライアントIPアドレスを取得
     *
     * @param array $serverData $_SERVER配列
     * @return string IPアドレス
     */
    private function getClientIp(array $serverData): string
    {
        // プロキシ経由の場合を考慮
        if (!empty($serverData['HTTP_X_FORWARDED_FOR'])) {
            $ips = explode(',', $serverData['HTTP_X_FORWARDED_FOR']);
            return trim($ips[0]);
        }

        if (!empty($serverData['HTTP_X_REAL_IP'])) {
            return $serverData['HTTP_X_REAL_IP'];
        }

        return $serverData['REMOTE_ADDR'] ?? '0.0.0.0';
    }

    /**
     * 特定期間のアクセス数を取得
     *
     * @param int $postId 投稿ID
     * @param string $since 開始日時（SQLiteのDATETIME形式）
     * @return int アクセス数
     */
    public function getAccessCount(int $postId, string $since = '-30 days'): int
    {
        if ($this->db === null) {
            return 0;
        }

        try {
            $stmt = $this->db->prepare("
                SELECT COUNT(*) as count
                FROM access_logs
                WHERE post_id = :post_id
                  AND accessed_at >= datetime('now', :since)
            ");

            $stmt->execute([
                'post_id' => $postId,
                'since' => $since,
            ]);

            $result = $stmt->fetch();
            return $result ? (int)$result['count'] : 0;
        } catch (\Exception $e) {
            error_log("AccessLogger::getAccessCount error: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * 古いログを削除（メンテナンス用）
     *
     * @param int $days 保持日数（この日数より古いログを削除）
     * @return int 削除された行数
     */
    public function cleanupOldLogs(int $days = 90): int
    {
        if ($this->db === null) {
            return 0;
        }

        try {
            $stmt = $this->db->prepare("
                DELETE FROM access_logs
                WHERE accessed_at < datetime('now', '-' || :days || ' days')
            ");

            $stmt->execute(['days' => $days]);
            return $stmt->rowCount();
        } catch (\Exception $e) {
            error_log("AccessLogger::cleanupOldLogs error: " . $e->getMessage());
            return 0;
        }
    }
}
