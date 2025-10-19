<?php

declare(strict_types=1);

namespace App\Security;

/**
 * レート制限クラス
 *
 * IPアドレスベースのレート制限を実装
 */
class RateLimiter
{
    private string $storageDir;
    private int $maxAttempts;
    private int $windowSeconds;

    /**
     * @param string $storageDir レート制限データの保存ディレクトリ
     * @param int $maxAttempts 許可する最大試行回数
     * @param int $windowSeconds 時間枠（秒）
     */
    public function __construct(string $storageDir, int $maxAttempts = 5, int $windowSeconds = 900)
    {
        $this->storageDir = $storageDir;
        $this->maxAttempts = $maxAttempts;
        $this->windowSeconds = $windowSeconds;

        // ディレクトリが存在しない場合は作成
        if (!is_dir($this->storageDir)) {
            mkdir($this->storageDir, 0755, true);
        }
    }

    /**
     * レート制限をチェック
     *
     * @param string $identifier 識別子（通常はIPアドレス）
     * @param string $action アクション名
     * @return bool 制限内の場合true、制限超過の場合false
     */
    public function check(string $identifier, string $action = 'default'): bool
    {
        $filePath = $this->getFilePath($identifier, $action);
        $now = time();

        // ファイルが存在しない場合は許可
        if (!file_exists($filePath)) {
            return true;
        }

        $data = $this->readData($filePath);

        // 古いエントリをクリーンアップ
        $data = $this->cleanupOldEntries($data, $now);

        // 制限内かチェック
        return count($data) < $this->maxAttempts;
    }

    /**
     * 試行を記録
     *
     * @param string $identifier 識別子（通常はIPアドレス）
     * @param string $action アクション名
     * @return void
     */
    public function record(string $identifier, string $action = 'default'): void
    {
        $filePath = $this->getFilePath($identifier, $action);
        $now = time();

        $data = [];
        if (file_exists($filePath)) {
            $data = $this->readData($filePath);
        }

        // 古いエントリをクリーンアップ
        $data = $this->cleanupOldEntries($data, $now);

        // 新しい試行を追加
        $data[] = $now;

        // ファイルに書き込み
        file_put_contents($filePath, json_encode($data), LOCK_EX);
    }

    /**
     * 残り試行回数を取得
     *
     * @param string $identifier 識別子
     * @param string $action アクション名
     * @return int 残り試行回数
     */
    public function getRemainingAttempts(string $identifier, string $action = 'default'): int
    {
        $filePath = $this->getFilePath($identifier, $action);
        $now = time();

        if (!file_exists($filePath)) {
            return $this->maxAttempts;
        }

        $data = $this->readData($filePath);
        $data = $this->cleanupOldEntries($data, $now);

        return max(0, $this->maxAttempts - count($data));
    }

    /**
     * 次に試行可能になる時刻を取得
     *
     * @param string $identifier 識別子
     * @param string $action アクション名
     * @return int|null UNIXタイムスタンプ、制限されていない場合はnull
     */
    public function getRetryAfter(string $identifier, string $action = 'default'): ?int
    {
        $filePath = $this->getFilePath($identifier, $action);
        $now = time();

        if (!file_exists($filePath)) {
            return null;
        }

        $data = $this->readData($filePath);
        $data = $this->cleanupOldEntries($data, $now);

        if (count($data) < $this->maxAttempts) {
            return null;
        }

        // 最も古い試行の時刻 + 時間枠
        return min($data) + $this->windowSeconds;
    }

    /**
     * データをリセット
     *
     * @param string $identifier 識別子
     * @param string $action アクション名
     * @return void
     */
    public function reset(string $identifier, string $action = 'default'): void
    {
        $filePath = $this->getFilePath($identifier, $action);
        if (file_exists($filePath)) {
            unlink($filePath);
        }
    }

    /**
     * ファイルパスを取得
     *
     * @param string $identifier 識別子
     * @param string $action アクション名
     * @return string ファイルパス
     */
    private function getFilePath(string $identifier, string $action): string
    {
        $hash = hash('sha256', $identifier . ':' . $action);
        return $this->storageDir . '/' . $hash . '.json';
    }

    /**
     * データを読み込み
     *
     * @param string $filePath ファイルパス
     * @return array タイムスタンプの配列
     */
    private function readData(string $filePath): array
    {
        $content = file_get_contents($filePath);
        if ($content === false) {
            return [];
        }

        $data = json_decode($content, true);
        return is_array($data) ? $data : [];
    }

    /**
     * 古いエントリをクリーンアップ
     *
     * @param array $data タイムスタンプの配列
     * @param int $now 現在時刻
     * @return array クリーンアップされたデータ
     */
    private function cleanupOldEntries(array $data, int $now): array
    {
        $cutoff = $now - $this->windowSeconds;
        return array_filter($data, function ($timestamp) use ($cutoff) {
            return $timestamp > $cutoff;
        });
    }
}
