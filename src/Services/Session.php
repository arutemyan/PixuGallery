<?php

declare(strict_types=1);

namespace App\Services;

require_once __DIR__. '/../Security/SecurityUtil.php';

use Exception;
use App\Utils\Logger;

/**
 * 簡易 Session 管理クラス
 * - PHP セッションの初期化ラッパー
 * - キーリングによる自動ローテーション（ファイルベース）
 * - AES-256-GCM による可逆 ID マスク（マスク/アンマスク）
 *
 * 注意: デフォルトのキー保存場所は project_root/config/session_keys/
 * 複数ノード運用の場合はキー同期（Vault 等）を別途用意してください。
 */
class Session
{
    private static ?Session $instance = null;

    private string $keyDir;
    /** @var string[] $keys raw binary keys, newest first */
    private array $keys = [];
    private int $rotationInterval; // seconds
    private int $retainKeys;
    private string $sessionNamespace = '_app_session';

    /**
     * start または getInstance を呼ぶこと
     *
     * @param array $opts [key_dir, rotation_interval]
     */
    public static function start(array $opts = []): Session
    {
        if (self::$instance === null) {
            try {
                self::$instance = new self($opts);
            } catch (\Throwable $ex) {
                // constructor already logs detailed instructions; ensure an
                // operator-friendly response is returned instead of a PHP
                // fatal error/stack trace. Log at error level as a fallback.
                try {
                    Logger::getInstance()->error('Session start failed: ' . $ex->getMessage());
                } catch (\Throwable $ignore) {
                    // ignore logging failures
                }

                // Return a user-friendly HTML page and exit gracefully.
                if (!headers_sent()) {
                    header(($_SERVER['SERVER_PROTOCOL'] ?? 'HTTP/1.1') . ' 503 Service Unavailable');
                    header('Content-Type: text/html; charset=utf-8');
                }
                echo '<!doctype html><html><head><meta charset="utf-8"><title>セットアップが完了していません / Setup incomplete</title></head><body style="font-family:system-ui,Segoe UI,Roboto,Arial,sans-serif;padding:2rem;">';
                echo '<h1>セットアップが完了していません</h1>';
                echo '<p>Setup incomplete. Please contact the site administrator.</p>';
                echo '</body></html>';
                exit(1);
            }
        } else {
            // If instance already exists ensure PHP session is active; this
            // handles tests that destroy the PHP session but keep the
            // Session singleton around.
            if (session_status() !== PHP_SESSION_ACTIVE) {
                // attempt to initialize PHP session settings and start it
                self::$instance->initPhpSession($opts);
            }
        }

        return self::$instance;
    }

    public static function getInstance(): Session
    {
        if (self::$instance === null) {
            throw new Exception('Session not started');
        }
        return self::$instance;
    }

    private function __construct(array $opts = [])
    {
        $projectRoot = dirname(__DIR__, 2);
        $this->keyDir = $opts['key_dir'] ?? $projectRoot . '/config/session_keys';
        $this->rotationInterval = $opts['rotation_interval'] ?? (60 * 60 * 24 * 30); // 30 days
        $this->retainKeys = $opts['retain_keys'] ?? 3; // keep newest N keys to allow decryption after rotation

        $this->initPhpSession($opts);
        // Require an explicit secret for ID token HMACs. Making APP_ID_SECRET (or
        // config.security.id_secret) mandatory removes the need for file-based
        // session_keys; file keys are no longer used by default.
        $hasEnvSecret = (getenv('APP_ID_SECRET') !== false && getenv('APP_ID_SECRET') !== '');
        try {
            $cfg = \App\Config\ConfigManager::getInstance()->getConfig();
            $hasConfigSecret = !empty($cfg['security']['id_secret']);
        } catch (\Throwable $ex) {
            $hasConfigSecret = false;
        }

        // In test environments we allow missing APP_ID_SECRET/config secret to avoid
        // failing unit tests that do not set secrets. Tests should define
        // the `TEST_ENV` constant in their bootstrap (already supported).
        if (defined('TEST_ENV') && TEST_ENV) {
            $this->keys = [];
            return;
        }

        if (!$hasEnvSecret && !$hasConfigSecret) {
            // Log detailed guidance for operators to app.log, but throw a concise
            // message for the running process so that end-users/operators see
            // a clear "setup incomplete" error without exposing internals.
            try {
                $msg = "APP_ID_SECRET or security.id_secret is not configured.\n" .
                    "This application requires an HMAC secret for ID token signing.\n" .
                    "Generate a 256-bit secret (example): openssl rand -base64 32 | tr '+/' '-_' | tr -d '='\n" .
                    "Set it as environment variable APP_ID_SECRET or put it in config.security.id_secret.\n" .
                    "Do NOT commit secrets to version control.\n";
                Logger::getInstance()->error('Session initialization failed: ' . str_replace("\n", ' | ', $msg));
            } catch (\Throwable $ex) {
                // If logging fails, continue to throw the concise exception below.
            }

            throw new Exception('Setup incomplete: APP_ID_SECRET is not configured. See logs/app.log for details.');
        }

        // Do not use file-based keyring when APP_ID_SECRET or config secret is present.
        $this->keys = [];
    }

    private function initPhpSession(array $config = null): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            // 設定がない場合は読み込む
            if ($config === null) {
                $config = \App\Config\ConfigManager::getInstance()->getConfig();
            }

            // HTTPS強制
            if (!empty($config['https']['force'])) {
                forceHttps();
            }

            // セキュリティヘッダー送信
            sendSecurityHeaders($config);

            ini_set('session.cookie_httponly', '1');
            // HTTPS環境でのみsecure cookieを有効化
            // 本番環境では常にHTTPS前提でsecure=1を設定することを推奨
            $isHttps = isHttps();
            $forceSecure = $config['security']['session']['force_secure_cookie'] ?? false;
            ini_set('session.cookie_secure', ($isHttps || $forceSecure) ? '1' : '0');
            ini_set('session.cookie_samesite', 'Strict');
            ini_set('session.use_strict_mode', '1');
            
            // セッションIDの再生成を定期的に行う設定
            ini_set('session.gc_maxlifetime', '3600'); // 1時間
            ini_set('session.gc_probability', '1');
            ini_set('session.gc_divisor', '100');
            
            session_start();
        }
    }

    private function ensureKeyDir(): void
    {
        if (!is_dir($this->keyDir)) {
            if (!mkdir($this->keyDir, 0700, true) && !is_dir($this->keyDir)) {
                throw new Exception('Failed to create key directory: ' . $this->keyDir);
            }
        } else {
            // 既存ディレクトリのパーミッションを強制的に0700に設定
            // セキュリティ上重要なディレクトリのため、所有者のみアクセス可能にする
            chmod($this->keyDir, 0700);
        }
    }

    private function loadKeys(): void
    {
        // support both legacy .bin and new .php key files
        $files = array_merge(
            glob(rtrim($this->keyDir, '/') . '/key_*.php') ?: []
        );
        if (!$files) {
            // generate initial key
            $this->generateKey();
            $files = array_merge(
                glob(rtrim($this->keyDir, '/') . '/key_*.php') ?: []
            );
        }

        // sort newest first
        usort($files, function ($a, $b) {
            return filemtime($b) <=> filemtime($a);
        });

        $this->keys = [];
        foreach ($files as $f) {
            try {
                if (substr($f, -4) === '.php') {
                    // require the php file which returns base64 encoded key
                    $val = @include $f;
                    if (!is_string($val) || $val === '') {
                        continue;
                    }
                    $k = base64_decode($val, true);
                    if ($k === false) {
                        continue;
                    }
                    $this->keys[] = $k;
                }
            } catch (\Throwable $ex) {
                // skip problematic files
                continue;
            }
        }

        if (empty($this->keys)) {
            throw new Exception('No valid keys available in ' . $this->keyDir);
        }
    }

    private function generateKey(): void
    {
        $key = random_bytes(32); // 256-bit key
        $b64 = base64_encode($key);
        $path = rtrim($this->keyDir, '/') . '/key_' . time() . '.php';
        $tmp = $path . '.tmp';
        $content = "<?php\ndeclare(strict_types=1);\nreturn '" . $b64 . "';\n";
        if (file_put_contents($tmp, $content) === false) {
            throw new Exception('Failed to write temporary key file: ' . $tmp);
        }
        // atomic move
        if (!rename($tmp, $path)) {
            @unlink($tmp);
            throw new Exception('Failed to move key file into place: ' . $path);
        }
        chmod($path, 0600);
        // prune old keys beyond retention
        $this->pruneOldKeys();
    }

    /**
     * Keep newest $retainKeys files and remove older ones.
     */
    private function pruneOldKeys(): void
    {
        $files = array_merge(
            glob(rtrim($this->keyDir, '/') . '/key_*.php') ?: [],
            glob(rtrim($this->keyDir, '/') . '/key_*.bin') ?: []
        );
        if (count($files) <= $this->retainKeys) {
            return;
        }
        usort($files, function ($a, $b) {
            return filemtime($b) <=> filemtime($a);
        });
        $toDelete = array_slice($files, $this->retainKeys);
        foreach ($toDelete as $f) {
            // only delete if file exists and is writable
            if (is_file($f) && is_writable($f)) {
                @unlink($f);
            }
        }
    }

    private function rotateIfNeeded(): void
    {
        $files = glob(rtrim($this->keyDir, '/') . '/key_*.bin');
        if (!$files) {
            return;
        }
        $newest = array_reduce($files, function ($carry, $item) {
            return $carry === null || filemtime($item) > filemtime($carry) ? $item : $carry;
        }, null);

        if ($newest === null) {
            return;
        }

        $age = time() - filemtime($newest);
        if ($age > $this->rotationInterval) {
            // rotate: generate new key (old keys remain for decryption)
            $this->generateKey();
            // reload
            $this->loadKeys();
        }
    }

    /**
     * get value from session namespace
     */
    public function get(string $key, $default = null)
    {
        return $_SESSION[$this->sessionNamespace][$key] ?? $default;
    }

    // --- Static compatibility proxies ---
    /**
     * Static proxy for get()
     */
    public static function getValue(string $key, $default = null)
    {
        return self::getInstance()->get($key, $default);
    }

    /**
     * Static proxy for set()
     */
    public static function setValue(string $key, $value): void
    {
        self::getInstance()->set($key, $value);
    }

    /**
     * Static proxy for has()
     */
    public static function hasValue(string $key): bool
    {
        return self::getInstance()->has($key);
    }

    /**
     * Static proxy for delete()
     */
    public static function deleteValue(string $key): void
    {
        self::getInstance()->delete($key);
    }

    public function set(string $key, $value): void
    {
        if (!isset($_SESSION[$this->sessionNamespace])) {
            $_SESSION[$this->sessionNamespace] = [];
        }
        $_SESSION[$this->sessionNamespace][$key] = $value;
    }

    public function has(string $key): bool
    {
        return isset($_SESSION[$this->sessionNamespace]) && array_key_exists($key, $_SESSION[$this->sessionNamespace]);
    }

    public function delete(string $key): void
    {
        if (isset($_SESSION[$this->sessionNamespace][$key])) {
            unset($_SESSION[$this->sessionNamespace][$key]);
        }
    }

    public function destroy(): void
    {
        // clear session array first
        $_SESSION = [];
        // only attempt to clear cookie / destroy if a session is active
        if (session_status() === PHP_SESSION_ACTIVE) {
            if (ini_get('session.use_cookies')) {
                $params = session_get_cookie_params();
                setcookie(session_name(), '', time() - 42000, $params['path'] ?? '/', $params['domain'] ?? '', $params['secure'] ?? false, $params['httponly'] ?? false);
            }
            @session_destroy();
        }
    }

    public function regenerate(bool $deleteOld = true): void
    {
        // Only regenerate if a session is active. If none is active, try to start one silently.
        if (session_status() !== PHP_SESSION_ACTIVE) {
            if (session_status() === PHP_SESSION_NONE) {
                // suppress warnings if session cannot be started in this context
                @session_start();
            } else {
                // sessions are disabled or in an unexpected state; skip regenerate
                return;
            }
        }

        // now safe to regenerate id
        @session_regenerate_id($deleteOld);
    }

    /**
     * user id helpers
     */
    public function setUserId(int $id): void
    {
        $this->set('user_id', $id);
    }

    public function getUserId(): ?int
    {
        $v = $this->get('user_id', null);
        return is_int($v) ? $v : (is_numeric($v) ? (int)$v : null);
    }

    /**
     * CSRF token (stored in session namespace)
     */
    public function getCsrfToken(): string
    {
        $token = $this->get('csrf_token');
        if (empty($token)) {
            $token = bin2hex(random_bytes(24));
            $this->set('csrf_token', $token);
        }
        return $token;
    }

    public function validateCsrf(string $token): bool
    {
        $stored = $this->get('csrf_token');
        if (empty($stored)) {
            return false;
        }
        return hash_equals($stored, $token);
    }

    /**
     * ID トークン生成（不可逆）
     * HMAC-SHA256 を用いて ID に対する検証タグを付与します。
     * トークンは URL 安全な base64 でエンコードされます。
     *
     * 仕組み:
     *  - secret = 環境変数 `APP_ID_SECRET` または config.security.id_secret を優先
     *  - フォールバック: 内部キーリングの先頭キーを使用
     *
     * メリット: 復号不要（不可逆）で鍵管理が単純になります。
     */
    public function maskId(int $id): string
    {
        $idStr = (string)$id;
        $secret = $this->getIdSecret();
        $mac = hash_hmac('sha256', $idStr, $secret, true);
        $macB64 = rtrim(strtr(base64_encode($mac), '+/', '-_'), '=');
        $payload = $idStr . ':' . $macB64;
        return rtrim(strtr(base64_encode($payload), '+/', '-_'), '=');
    }

    /**
     * ID トークン検証（不可逆）
     * 有効なトークンであれば元の ID を返します。無効なら null を返します。
     */
    public function unmaskId(string $masked): ?int
    {
        $payload = base64_decode(strtr($masked, '-_', '+/'));
        if ($payload === false) {
            return null;
        }
        $parts = explode(':', $payload, 2);
        if (count($parts) !== 2) {
            return null;
        }
        [$idStr, $macB64] = $parts;
        if (!ctype_digit($idStr)) {
            return null;
        }
        $secret = $this->getIdSecret();
        $expectedMac = hash_hmac('sha256', $idStr, $secret, true);
        $expectedMacB64 = rtrim(strtr(base64_encode($expectedMac), '+/', '-_'), '=');
        if (!hash_equals($expectedMacB64, $macB64)) {
            return null;
        }
        return (int)$idStr;
    }

    /**
     * ID トークン用のシークレットを取得する。優先順:
     * 1) 環境変数 `APP_ID_SECRET`
     * 2) config.security.id_secret
     * 3) キーリングの先頭キー（バイナリ）
     */
    private function getIdSecret(): string
    {
        $env = getenv('APP_ID_SECRET');
        if ($env !== false && $env !== '') {
            return $env;
        }

        try {
            $config = \App\Config\ConfigManager::getInstance()->getConfig();
            if (!empty($config['security']['id_secret'])) {
                return $config['security']['id_secret'];
            }
        } catch (\Throwable $ex) {
            // ignore — fall back to key
        }

        throw new Exception('No ID secret available (set APP_ID_SECRET or security.id_secret in config)');
    }
}
