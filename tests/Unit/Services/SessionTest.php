<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use App\Services\Session;

final class SessionTest extends TestCase
{
    private string $keyDir;

    protected function setUp(): void
    {
        // Ensure APP_ID_SECRET is set for tests (required by Session)
        if (getenv('APP_ID_SECRET') === false || getenv('APP_ID_SECRET') === '') {
            // 256-bit secret in hex
            $secret = bin2hex(random_bytes(32));
            putenv('APP_ID_SECRET=' . $secret);
        }

        $this->keyDir = sys_get_temp_dir() . '/session_keys_test_' . uniqid('', true);
        if (!is_dir($this->keyDir)) {
            mkdir($this->keyDir, 0700, true);
        }

        if (session_status() !== PHP_SESSION_NONE) {
            session_unset();
            session_destroy();
        }

        // Reset singleton instance between tests
        $rc = new ReflectionClass(Session::class);
        $prop = $rc->getProperty('instance');
        $prop->setAccessible(true);
        $prop->setValue(null);
    }

    protected function tearDown(): void
    {
        // Unset APP_ID_SECRET to avoid leaking between tests
        putenv('APP_ID_SECRET');

        if (session_status() !== PHP_SESSION_NONE) {
            session_unset();
            session_destroy();
        }

        $rc = new ReflectionClass(Session::class);
        $prop = $rc->getProperty('instance');
        $prop->setAccessible(true);
        $prop->setValue(null);

        $this->rrmdir($this->keyDir);
    }

    private function rrmdir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($it as $file) {
            if ($file->isDir()) {
                rmdir($file->getRealPath());
            } else {
                unlink($file->getRealPath());
            }
        }
        @rmdir($dir);
    }

    public function testSetGetUserId(): void
    {
        $s = Session::start(['key_dir' => $this->keyDir]);
        $s->setUserId(123);
        $this->assertSame(123, $s->getUserId());
    }

    public function testMaskUnmask(): void
    {
        $s = Session::start(['key_dir' => $this->keyDir]);
        $masked = $s->maskId(42);
        $this->assertIsString($masked);
        $this->assertSame(42, $s->unmaskId($masked));
    }

    public function testCsrfToken(): void
    {
        $s = Session::start(['key_dir' => $this->keyDir]);
        $t1 = $s->getCsrfToken();
        $this->assertIsString($t1);
        $t2 = $s->getCsrfToken();
        $this->assertSame($t1, $t2);
        $this->assertTrue($s->validateCsrf($t1));
        $this->assertFalse($s->validateCsrf('invalid-token'));
    }

    public function testSessionStorage(): void
    {
        $s = Session::start(['key_dir' => $this->keyDir]);
        $s->set('foo', 'bar');
        $this->assertSame('bar', $s->get('foo'));
    }
}
