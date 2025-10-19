<?php

declare(strict_types=1);

namespace App\Models;

use App\Database\Connection;
use PDO;

/**
 * 設定モデルクラス
 *
 * サイト設定のCRUD操作を管理
 */
class Setting
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Connection::getInstance();
    }

    /**
     * 設定値を取得
     *
     * @param string $key 設定キー
     * @param string $default デフォルト値
     * @return string 設定値、存在しない場合はデフォルト値
     */
    public function get(string $key, string $default = ''): string
    {
        $stmt = $this->db->prepare("SELECT value FROM settings WHERE key = ?");
        $stmt->execute([$key]);
        $result = $stmt->fetch();
        return $result ? $result['value'] : $default;
    }

    /**
     * 設定値を保存（既存の場合は更新）
     *
     * @param string $key 設定キー
     * @param string $value 設定値
     * @return bool 成功した場合true
     */
    public function set(string $key, string $value): bool
    {
        $stmt = $this->db->prepare("
            INSERT INTO settings (key, value)
            VALUES (?, ?)
            ON CONFLICT(key) DO UPDATE SET value = ?, updated_at = CURRENT_TIMESTAMP
        ");
        return $stmt->execute([$key, $value, $value]);
    }

    /**
     * すべての設定を取得
     *
     * @return array 設定データの配列
     */
    public function getAll(): array
    {
        $stmt = $this->db->query("SELECT key, value FROM settings");
        return $stmt->fetchAll();
    }
}
