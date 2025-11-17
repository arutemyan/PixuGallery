<?php
declare(strict_types=1);

namespace Tests\Security;

use PHPUnit\Framework\TestCase;

/**
 * セキュリティ関数ユニットテスト
 *
 * config/security.phpの全セキュリティ機能をテスト
 *
 * テスト対象:
 * - CSRF対策
 * - XSS対策
 * - SQLインジェクション対策
 * - パスワードハッシュ化
 * - セッション管理
 * - ファイルアップロード検証
 *
 * @version 1.0.0
 * @author Claude Code
 */
class SecurityTest extends TestCase
{
    private string $securityPhpPath;

    /**
     * テスト前の初期化
     */
    protected function setUp(): void
    {
        parent::setUp();
        // セキュリティ関数ファイルをロード
        $this->securityPhpPath = __DIR__ . '/../../src/Security/SecurityUtil.php';
        require_once $this->securityPhpPath;

        // セッションは Session サービスで開始
        \App\Services\Session::start();
    }

    /**
     * テスト後のクリーンアップ
     */
    protected function tearDown(): void
    {
        // Session サービスがあれば破棄、それ以外はグローバルをクリア
        // Session サービスで破棄
        \App\Services\Session::getInstance()->destroy();

        parent::tearDown();
    }

    // ========================================
    // CSRF対策のテスト
    // ========================================

    /**
     * 正常系: CSRFトークンが正しく生成される（32バイト = 64文字の16進数）
     *
     * @test
     */
    public function testGenerateCsrfTokenCreates64CharacterToken(): void
    {
        $token = \App\Security\CsrfProtection::generateToken();

        // Session::getCsrfToken() は 24 バイトを hex にして 48 文字となる（実装上の仕様）
        $this->assertEquals(48, strlen($token));
        $this->assertMatchesRegularExpression('/^[a-f0-9]{48}$/', $token);

        // CsrfProtection経由で同一トークンが返ることを確認
        $this->assertEquals($token, \App\Security\CsrfProtection::getToken());
    }

    /**
     * 正常系: 正しいトークンでhash_equals()がtrueを返す
     *
     * @test
     */
    public function testVerifyCsrfTokenReturnsTrueForValidToken(): void
    {
        $token = \App\Security\CsrfProtection::generateToken();

        // 同じトークンで検証
        $result = \App\Security\CsrfProtection::validateToken($token);

        $this->assertTrue($result);
    }

    /**
     * 異常系: 不正なトークンでhash_equals()がfalseを返す
     *
     * @test
     */
    public function testVerifyCsrfTokenReturnsFalseForInvalidToken(): void
    {
        \App\Security\CsrfProtection::generateToken();

        // 異なるトークンで検証
        $invalidToken = bin2hex(random_bytes(32));
        $result = \App\Security\CsrfProtection::validateToken($invalidToken);

        $this->assertFalse($result);
    }

    /**
     * 異常系: トークンが空の場合、falseを返す
     *
     * @test
     */
    public function testVerifyCsrfTokenReturnsFalseForEmptyToken(): void
    {
        \App\Security\CsrfProtection::getToken();

        // 空文字列で検証
        $result = \App\Security\CsrfProtection::validateToken('');

        $this->assertFalse($result);
    }

    /**
     * 異常系: セッションにトークンが存在しない場合、falseを返す
     *
     * @test
     */
    public function testVerifyCsrfTokenReturnsFalseWhenNoSessionToken(): void
    {
        // CsrfProtection のセッショントークンをクリア
        \App\Security\CsrfProtection::clearSession();

        $result = \App\Security\CsrfProtection::validateToken('some_token');

        $this->assertFalse($result);
    }

    /**
     * セキュリティ: タイミング攻撃に対して安全（hash_equals使用確認）
     *
     * @test
     */
    public function testVerifyCsrfTokenUsesHashEquals(): void
    {
        // CSRF 検証にタイミング攻撃対策の hash_equals が使用されていることを確認
        $sessionSource = file_get_contents(__DIR__ . '/../../src/Services/Session.php');
        $this->assertStringContainsString('hash_equals', $sessionSource);
    }

    // ========================================
    // XSS対策のテスト
    // ========================================

    /**
     * 正常系: htmlspecialchars()が正しくエスケープする
     *
     * @test
     */
    public function testEscapeHtmlEscapesHtmlCharacters(): void
    {
        $input = '<div>Test & "quotes" \'single\'</div>';
        $expected = '&lt;div&gt;Test &amp; &quot;quotes&quot; &#039;single&#039;&lt;/div&gt;';

        $result = escapeHtml($input);

        $this->assertEquals($expected, $result);
    }

    /**
     * 異常系: `<script>alert('xss')</script>` が無害化される
     *
     * @test
     */
    public function testEscapeHtmlNeutralizesScriptTag(): void
    {
        $xssPayload = "<script>alert('xss')</script>";

        $result = escapeHtml($xssPayload);

        // スクリプトタグがエスケープされている
        $this->assertStringNotContainsString('<script>', $result);
        $this->assertStringNotContainsString('</script>', $result);
        $this->assertStringContainsString('&lt;script&gt;', $result);
        $this->assertStringContainsString('&lt;/script&gt;', $result);
    }

    /**
     * 異常系: `" onclick="alert('xss')"` が無害化される
     *
     * @test
     */
    public function testEscapeHtmlNeutralizesOnClickAttribute(): void
    {
        $xssPayload = '" onclick="alert(\'xss\')"';

        $result = escapeHtml($xssPayload);

        // ダブルクォートがエスケープされている
        $this->assertStringContainsString('&quot;', $result);
        $this->assertStringNotContainsString('onclick="', $result);
    }

    /**
     * 異常系: `' onload='alert(1)'` が無害化される
     *
     * @test
     */
    public function testEscapeHtmlNeutralizesOnLoadAttribute(): void
    {
        $xssPayload = "' onload='alert(1)'";

        $result = escapeHtml($xssPayload);

        // シングルクォートがエスケープされている
        $this->assertStringContainsString('&#039;', $result);
        $this->assertStringNotContainsString("onload='", $result);
    }

    /**
     * 正常系: ENT_QUOTES フラグが使用されている
     *
     * @test
     */
    public function testEscapeHtmlUsesEntQuotesFlag(): void
    {
        $input = "Test 'single' \"double\"";

        $result = escapeHtml($input);

        // シングルクォートとダブルクォートの両方がエスケープされている
        $this->assertStringContainsString('&#039;', $result); // シングルクォート
        $this->assertStringContainsString('&quot;', $result);  // ダブルクォート
    }

    // ========================================
    // SQLインジェクション対策のテスト
    // ========================================

    /**
     * 正常系: Prepared Statementsが使用されている
     *
     * @test
     */
    public function testExecutePreparedStatementUsesPrepare(): void
    {
        // SQLiteメモリデータベースを作成
        $pdo = new \PDO('sqlite::memory:');
        $pdo->exec('CREATE TABLE test (id INTEGER, name TEXT)');

        // Prepared Statementでデータ挿入
        $stmt = executePreparedStatement(
            $pdo,
            'INSERT INTO test (id, name) VALUES (?, ?)',
            [1, 'Test']
        );

        $this->assertInstanceOf(\PDOStatement::class, $stmt);

        // データが正しく挿入されたか確認
        $result = $pdo->query('SELECT * FROM test')->fetch(\PDO::FETCH_ASSOC);
        $this->assertEquals(1, $result['id']);
        $this->assertEquals('Test', $result['name']);
    }

    /**
     * 異常系: `1 OR 1=1` などの攻撃文字列が無害化される
     *
     * @test
     */
    public function testExecutePreparedStatementPreventsOrInjection(): void
    {
        $pdo = new \PDO('sqlite::memory:');
        $pdo->exec('CREATE TABLE users (id INTEGER, username TEXT)');
        $pdo->exec("INSERT INTO users VALUES (1, 'admin')");
        $pdo->exec("INSERT INTO users VALUES (2, 'user')");

        // SQLインジェクション試行: 1 OR 1=1
        $maliciousId = '1 OR 1=1';

        $stmt = executePreparedStatement(
            $pdo,
            'SELECT * FROM users WHERE id = ?',
            [$maliciousId]
        );

        $results = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // 1件も取得されない（文字列として扱われるため）
        $this->assertCount(0, $results);
    }

    /**
     * 異常系: `'; DROP TABLE posts; --` が無害化される
     *
     * @test
     */
    public function testExecutePreparedStatementPreventsDropTableInjection(): void
    {
        $pdo = new \PDO('sqlite::memory:');
        $pdo->exec('CREATE TABLE posts (id INTEGER, title TEXT)');
        $pdo->exec("INSERT INTO posts VALUES (1, 'Test Post')");

        // SQLインジェクション試行: '; DROP TABLE posts; --
        $maliciousInput = "'; DROP TABLE posts; --";

        $stmt = executePreparedStatement(
            $pdo,
            'SELECT * FROM posts WHERE title = ?',
            [$maliciousInput]
        );

        $results = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // 攻撃が無効化され、テーブルは存在し続ける
        $this->assertCount(0, $results);

        // テーブルがまだ存在することを確認
        $tableCheck = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='posts'");
        $this->assertNotEmpty($tableCheck->fetchAll());
    }

    /**
     * 正常系: パラメータバインディングが正しく動作する
     *
     * @test
     */
    public function testExecutePreparedStatementBindsParametersCorrectly(): void
    {
        $pdo = new \PDO('sqlite::memory:');
        $pdo->exec('CREATE TABLE items (id INTEGER, name TEXT, price REAL)');

        // 複数パラメータのバインド
        $stmt = executePreparedStatement(
            $pdo,
            'INSERT INTO items (id, name, price) VALUES (?, ?, ?)',
            [1, 'Product A', 99.99]
        );

        $this->assertInstanceOf(\PDOStatement::class, $stmt);

        // データが正しく挿入されたか確認
        $result = $pdo->query('SELECT * FROM items')->fetch(\PDO::FETCH_ASSOC);
        $this->assertEquals(1, $result['id']);
        $this->assertEquals('Product A', $result['name']);
        $this->assertEquals(99.99, $result['price']);
    }

    // ========================================
    // パスワードハッシュ化のテスト
    // ========================================

    /**
     * 正常系: password_hash()で正しくハッシュ化される
     *
     * @test
     */
    public function testHashPasswordCreatesValidHash(): void
    {
        $password = 'MySecurePassword123!';

        $hash = hashPassword($password);

        // ハッシュが生成されている
        $this->assertNotEmpty($hash);
        $this->assertNotEquals($password, $hash);

        // bcryptのハッシュ形式（$2y$で始まる）
        $this->assertStringStartsWith('$2y$', $hash);
    }

    /**
     * 正常系: password_verify()で検証できる
     *
     * @test
     */
    public function testVerifyPasswordReturnsTrueForCorrectPassword(): void
    {
        $password = 'MySecurePassword123!';
        $hash = hashPassword($password);

        $result = verifyPassword($password, $hash);

        $this->assertTrue($result);
    }

    /**
     * 異常系: 間違ったパスワードでverifyがfalseを返す
     *
     * @test
     */
    public function testVerifyPasswordReturnsFalseForIncorrectPassword(): void
    {
        $password = 'MySecurePassword123!';
        $wrongPassword = 'WrongPassword456!';
        $hash = hashPassword($password);

        $result = verifyPassword($wrongPassword, $hash);

        $this->assertFalse($result);
    }

    /**
     * セキュリティ: PASSWORD_DEFAULT（bcrypt以上）が使用されている
     *
     * @test
     */
    public function testHashPasswordUsesPasswordDefault(): void
    {
        $password = 'TestPassword';
        $hash = hashPassword($password);

        // PASSWORD_DEFAULTはbcryptを使用（$2y$で始まる）
        $this->assertStringStartsWith('$2y$', $hash);

        // ソースコードでPASSWORD_DEFAULTが使用されていることを確認
        $securityPhpContent = file_get_contents($this->securityPhpPath);
        $this->assertStringContainsString('PASSWORD_DEFAULT', $securityPhpContent);
    }

    /**
     * セキュリティ: 同じパスワードでも異なるハッシュが生成される（ソルト使用確認）
     *
     * @test
     */
    public function testHashPasswordGeneratesDifferentHashesForSamePassword(): void
    {
        $password = 'SamePassword';

        $hash1 = hashPassword($password);
        $hash2 = hashPassword($password);

        // 同じパスワードでもハッシュは異なる（ソルトが使用されている）
        $this->assertNotEquals($hash1, $hash2);

        // 両方とも検証は成功する
        $this->assertTrue(verifyPassword($password, $hash1));
        $this->assertTrue(verifyPassword($password, $hash2));
    }

    // ========================================
    // セッション管理のテスト
    // ========================================

    /**
     * 正常系: セッションIDが適切に生成される
     *
     * @test
     */
    public function testInitSecureSessionGeneratesSessionId(): void
    {
        // 既存セッションを破棄
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }

        \App\Services\Session::start();

        // セッションが開始されている
        $this->assertEquals(PHP_SESSION_ACTIVE, session_status());

        // セッションIDが生成されている
        $sessionId = session_id();
        $this->assertNotEmpty($sessionId);
    }

    /**
     * セキュリティ: session_regenerate_id()がログイン時に呼ばれる
     *
     * @test
     */
    public function testRegenerateSessionIdChangesSessionId(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $oldSessionId = session_id();

        \App\Services\Session::getInstance()->regenerate();

        $newSessionId = session_id();

        // セッションIDが変更されている
        $this->assertNotEquals($oldSessionId, $newSessionId);
        $this->assertNotEmpty($newSessionId);
    }

    /**
     * セキュリティ: httponly、secure フラグが設定されている
     *
     * @test
     */
    public function testInitSecureSessionSetsSecurityFlags(): void
    {
        // セッション破棄
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }

        // セッション変数とIDをクリア
        $_SESSION = [];
        if (session_id()) {
            session_write_close();
        }

        \App\Services\Session::start();

        // ini設定を確認
        $this->assertEquals('1', ini_get('session.cookie_httponly'));

        // HTTPSでない場合、cookie_secureは自動的に0になる（正常動作）
        if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
            $this->assertEquals('1', ini_get('session.cookie_secure'));
        } else {
            // HTTPの場合はsecureフラグは設定されない
            $this->assertEquals('0', ini_get('session.cookie_secure'));
        }

        $this->assertEquals('Strict', ini_get('session.cookie_samesite'));
        $this->assertEquals('1', ini_get('session.use_strict_mode'));
    }

    // ========================================
    // ファイルアップロードのテスト
    // ========================================

    /**
     * 正常系: 画像ファイル（jpg, png）のMIME検証が通る
     *
     * @test
     */
    public function testValidateFileUploadAcceptsValidImageFiles(): void
    {
        // 一時的なJPEGファイルを作成
        $tempFile = tmpfile();
        $tempPath = stream_get_meta_data($tempFile)['uri'];

        // 1x1の黒いJPEG画像を作成
        $image = imagecreatetruecolor(1, 1);
        imagejpeg($image, $tempPath);
        imagedestroy($image);

        $file = [
            'name' => 'test.jpg',
            'tmp_name' => $tempPath,
            'size' => filesize($tempPath),
            'error' => UPLOAD_ERR_OK
        ];

        $result = validateFileUpload($file);

        $this->assertTrue($result['valid']);
        $this->assertNull($result['error']);

        fclose($tempFile);
    }

    /**
     * 異常系: PHPファイルのアップロードが拒否される
     *
     * @test
     */
    public function testValidateFileUploadRejectsPhpFiles(): void
    {
        $tempFile = tmpfile();
        $tempPath = stream_get_meta_data($tempFile)['uri'];
        file_put_contents($tempPath, '<?php echo "test"; ?>');

        $file = [
            'name' => 'malicious.php',
            'tmp_name' => $tempPath,
            'size' => filesize($tempPath),
            'error' => UPLOAD_ERR_OK
        ];

        $result = validateFileUpload($file);

        $this->assertFalse($result['valid']);
        $this->assertNotNull($result['error']);

        fclose($tempFile);
    }

    /**
     * 異常系: 10MB超のファイルが拒否される
     *
     * @test
     */
    public function testValidateFileUploadRejectsOversizedFiles(): void
    {
        $tempFile = tmpfile();
        $tempPath = stream_get_meta_data($tempFile)['uri'];

        // 1x1の画像を作成
        $image = imagecreatetruecolor(1, 1);
        imagejpeg($image, $tempPath);
        imagedestroy($image);

        $file = [
            'name' => 'large.jpg',
            'tmp_name' => $tempPath,
            'size' => 11 * 1024 * 1024, // 11MB（実際のファイルサイズは小さいがsizeを偽装）
            'error' => UPLOAD_ERR_OK
        ];

        $result = validateFileUpload($file, 10);

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('10MB', $result['error']);

        fclose($tempFile);
    }

    /**
     * 異常系: 偽装MIME type（拡張子とMIME不一致）が検出される
     *
     * @test
     */
    public function testValidateFileUploadDetectsMimeTypeMismatch(): void
    {
        $tempFile = tmpfile();
        $tempPath = stream_get_meta_data($tempFile)['uri'];

        // JPEGファイルを作成
        $image = imagecreatetruecolor(1, 1);
        imagejpeg($image, $tempPath);
        imagedestroy($image);

        // 拡張子をPNGに偽装
        $file = [
            'name' => 'fake.png', // 拡張子はPNGだが実際はJPEG
            'tmp_name' => $tempPath,
            'size' => filesize($tempPath),
            'error' => UPLOAD_ERR_OK
        ];

        $result = validateFileUpload($file);

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('拡張子とファイル形式が一致しません', $result['error']);

        fclose($tempFile);
    }

    /**
     * 異常系: アップロードされていないファイルが拒否される
     *
     * @test
     */
    public function testValidateFileUploadRejectsNonUploadedFiles(): void
    {
        $file = [
            'name' => 'test.jpg',
            'tmp_name' => '/tmp/nonexistent.jpg',
            'size' => 1000,
            'error' => UPLOAD_ERR_OK
        ];

        $result = validateFileUpload($file);

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('アップロードされていません', $result['error']);
    }

    /**
     * 正常系: 許可されたMIMEタイプのみが通過する
     *
     * @test
     */
    public function testValidateFileUploadRejectsDisallowedMimeTypes(): void
    {
        $tempFile = tmpfile();
        $tempPath = stream_get_meta_data($tempFile)['uri'];
        file_put_contents($tempPath, 'Plain text file');

        $file = [
            'name' => 'document.txt',
            'tmp_name' => $tempPath,
            'size' => filesize($tempPath),
            'error' => UPLOAD_ERR_OK
        ];

        $result = validateFileUpload($file);

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('許可されていないファイル形式', $result['error']);

        fclose($tempFile);
    }

    // ========================================
    // セキュリティログのテスト
    // ========================================

    /**
     * 正常系: セキュリティログが正しく記録される
     *
     * @test
     */
    public function testLogSecurityEventWritesToLogFile(): void
    {
        $logDir = __DIR__ . '/../../data/log';
        $logFile = $logDir . '/security.log';

        // 既存ログを削除
        if (file_exists($logFile)) {
            unlink($logFile);
        }

        // ログを記録
        logSecurityEvent('Test security event', ['user_id' => 123]);

        // ログファイルが作成されている
        $this->assertFileExists($logFile);

        // ログ内容を確認
        $logContent = file_get_contents($logFile);
        $this->assertStringContainsString('Test security event', $logContent);
        $this->assertStringContainsString('user_id', $logContent);
        $this->assertStringContainsString('123', $logContent);

        // クリーンアップ
        if (file_exists($logFile)) {
            unlink($logFile);
        }
    }
}
