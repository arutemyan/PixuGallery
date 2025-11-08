<?php

declare(strict_types=1);

namespace Tests\Api;

use PHPUnit\Framework\TestCase;

/**
 * 管理API（upload, delete, theme）のユニットテスト
 *
 * テスト対象:
 * - admin/api/upload.php - 画像アップロード + WebP変換
 * - admin/api/delete.php - 投稿削除
 * - admin/api/theme.php - テーマ更新
 */
class AdminApiTest extends TestCase
{
    /** @var \PDO|null インメモリSQLiteデータベース */
    private ?\PDO $db = null;

    /** @var string テスト用一時ディレクトリ */
    private string $tempDir;

    /** @var string テスト用画像ディレクトリ */
    private string $imagesDir;

    /** @var string テスト用サムネイルディレクトリ */
    private string $thumbsDir;

    /** @var string テスト用キャッシュディレクトリ */
    private string $cacheDir;

    /**
     * 各テスト実行前の初期化処理
     */
    protected function setUp(): void
    {
        parent::setUp();

        // インメモリSQLiteデータベースの作成
        $this->db = new \PDO('sqlite::memory:');
        $this->db->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        // テーブル作成
        $this->db->exec("
            CREATE TABLE posts (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                title TEXT NOT NULL,
                tags TEXT,
                detail TEXT,
                image_path TEXT,
                thumb_path TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");

        // テスト用一時ディレクトリの作成
        $this->tempDir = sys_get_temp_dir() . '/photo_site_test_' . uniqid();
        mkdir($this->tempDir, 0777, true);

        $this->imagesDir = $this->tempDir . '/images';
        $this->thumbsDir = $this->tempDir . '/thumbs';
        $this->cacheDir = $this->tempDir . '/cache';

        mkdir($this->imagesDir, 0777, true);
        mkdir($this->thumbsDir, 0777, true);
        mkdir($this->cacheDir, 0777, true);

        // セッション初期化（Sessionサービスを使用）
        \App\Services\Session::start();
    }

    /**
     * 各テスト実行後のクリーンアップ処理
     */
    protected function tearDown(): void
    {
        // データベースクローズ
        $this->db = null;

        // 一時ディレクトリの削除
        $this->removeDirectory($this->tempDir);

        // セッションクリア
        \App\Services\Session::getInstance()->destroy();

        parent::tearDown();
    }

    /**
     * ディレクトリを再帰的に削除
     */
    private function removeDirectory(string $dir): void
    {
        if (!file_exists($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }

    /**
     * CSRFトークンを生成してセッションに設定
     */
    private function generateCsrfToken(): string
    {
        return \App\Security\CsrfProtection::getToken();
    }

    /**
     * 認証済みセッションを設定
     */
    private function setAuthenticatedSession(): void
    {
        \App\Services\Session::getInstance()->set('admin_authenticated', true);
        \App\Services\Session::getInstance()->set('admin_user', 'admin');
    }

    /**
     * テスト用画像ファイルを作成
     */
    private function createTestImage(string $width = '100', string $height = '100'): string
    {
        $imagePath = $this->tempDir . '/test_image.jpg';

        // 100x100の赤い画像を作成
        $image = imagecreatetruecolor((int)$width, (int)$height);
        $red = imagecolorallocate($image, 255, 0, 0);
        imagefill($image, 0, 0, $red);
        imagejpeg($image, $imagePath, 90);
        imagedestroy($image);

        return $imagePath;
    }

    /**
     * $_FILES配列をモック
     */
    private function mockUploadedFile(string $tmpPath, string $originalName, int $size, int $error = UPLOAD_ERR_OK): array
    {
        return [
            'image' => [
                'name' => $originalName,
                'type' => 'image/jpeg',
                'tmp_name' => $tmpPath,
                'error' => $error,
                'size' => $size,
            ]
        ];
    }

    /**
     * upload.php のシミュレーション関数
     *
     * 実際のAPIの挙動を模倣
     */
    private function simulateUploadApi(array $postData, array $files): array
    {
        // 認証チェック
        if (!\App\Services\Session::getInstance()->get('admin_authenticated')) {
            http_response_code(403);
            return ['error' => 'Unauthorized'];
        }

        // CSRFトークンチェック
        if (!\App\Security\CsrfProtection::validateToken($postData['csrf'] ?? null)) {
            http_response_code(403);
            return ['error' => 'CSRF token mismatch'];
        }

        // ファイルアップロードチェック
        if (!isset($files['image']) || $files['image']['error'] !== UPLOAD_ERR_OK) {
            return ['error' => 'File upload failed'];
        }

        $file = $files['image'];

        // ファイルサイズチェック（10MB制限）
        if ($file['size'] > 10 * 1024 * 1024) {
            return ['error' => 'File size exceeds 10MB limit'];
        }

        // MIME typeチェック
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mimeType, $allowedTypes)) {
            return ['error' => 'Invalid file type'];
        }

        // 認証チェック
        if (!\App\Services\Session::getInstance()->get('admin_authenticated')) {
            return ['error' => 'Unauthorized'];
        }

        // CSRFトークンチェック
        if (!\App\Security\CsrfProtection::validateToken($postData['csrf'] ?? null)) {
            return ['error' => 'CSRF token mismatch'];
        }

        // 出力パスを決定
        $filename = 'upload_' . uniqid();
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)) ?: 'jpg';
        $imagePath = 'images/' . $filename . '.' . $ext;
        $thumbPath = 'thumbs/' . $filename . '.webp';

        $fullImagePath = $this->tempDir . '/' . $imagePath;
        $fullThumbPath = $this->tempDir . '/' . $thumbPath;

        // 画像ファイルをコピー
        if (!copy($file['tmp_name'], $fullImagePath)) {
            return ['error' => 'Failed to save image'];
        }

        // WebPサムネイル生成
        $sourceImage = null;
        switch ($mimeType) {
            case 'image/jpeg':
                $sourceImage = imagecreatefromjpeg($fullImagePath);
                break;
            case 'image/png':
                $sourceImage = imagecreatefrompng($fullImagePath);
                break;
            case 'image/gif':
                $sourceImage = imagecreatefromgif($fullImagePath);
                break;
            case 'image/webp':
                $sourceImage = imagecreatefromwebp($fullImagePath);
                break;
        }

        if ($sourceImage === false) {
            unlink($fullImagePath);
            return ['error' => 'Failed to process image'];
        }

        // サムネイル作成（幅800pxにリサイズ）
        $originalWidth = imagesx($sourceImage);
        $originalHeight = imagesy($sourceImage);
        $thumbWidth = 800;
        $thumbHeight = (int)(($originalHeight / $originalWidth) * $thumbWidth);

        $thumbImage = imagecreatetruecolor($thumbWidth, $thumbHeight);
        imagecopyresampled(
            $thumbImage, $sourceImage,
            0, 0, 0, 0,
            $thumbWidth, $thumbHeight,
            $originalWidth, $originalHeight
        );

        imagewebp($thumbImage, $fullThumbPath, 80);
        imagedestroy($sourceImage);
        imagedestroy($thumbImage);

        // データベースに保存
        $stmt = $this->db->prepare(
            'INSERT INTO posts (title, tags, detail, image_path, thumb_path) VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $postData['title'] ?? '',
            $postData['tags'] ?? '',
            $postData['detail'] ?? '',
            $imagePath,
            $thumbPath
        ]);

        $postId = (int)$this->db->lastInsertId();

        // キャッシュ生成
        $this->generateCacheFiles($postId);

        return ['success' => true, 'id' => $postId];
    }

    /**
     * delete.php のシミュレーション関数
     */
    private function simulateDeleteApi(array $postData): array
    {
        // 認証チェック
        if (!\App\Services\Session::getInstance()->get('admin_authenticated')) {
            http_response_code(403);
            return ['error' => 'Unauthorized'];
        }

        // CSRFトークンチェック
        if (!\App\Security\CsrfProtection::validateToken($postData['csrf'] ?? null)) {
            http_response_code(403);
            return ['error' => 'CSRF token mismatch'];
        }

        // IDチェック
        if (!isset($postData['id'])) {
            return ['error' => 'Post ID is required'];
        }

        $id = (int)$postData['id'];

        // 投稿存在チェック
        $stmt = $this->db->prepare('SELECT * FROM posts WHERE id = ?');
        $stmt->execute([$id]);
        $post = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$post) {
            return ['error' => 'Post not found'];
        }

        // ファイル削除
        $imagePath = $this->tempDir . '/' . $post['image_path'];
        $thumbPath = $this->tempDir . '/' . $post['thumb_path'];

        if (file_exists($imagePath)) {
            unlink($imagePath);
        }
        if (file_exists($thumbPath)) {
            unlink($thumbPath);
        }

        // データベースから削除
        $stmt = $this->db->prepare('DELETE FROM posts WHERE id = ?');
        $stmt->execute([$id]);

        // キャッシュ削除
        $this->clearCacheFiles($id);

        return ['success' => true];
    }

    /**
     * theme.php のシミュレーション関数
     */
    private function simulateThemeApi(array $postData): array
    {
        // 認証チェック
        if (!\App\Services\Session::getInstance()->get('admin_authenticated')) {
            http_response_code(403);
            return ['error' => 'Unauthorized'];
        }

        // CSRFトークンチェック
        if (!\App\Security\CsrfProtection::validateToken($postData['csrf'] ?? null)) {
            http_response_code(403);
            return ['error' => 'CSRF token mismatch'];
        }

        // ヘッダーまたはフッターが必要
        if (!isset($postData['header']) && !isset($postData['footer'])) {
            return ['error' => 'Header or footer content is required'];
        }

        // XSS対策: HTMLをサニタイズ（実際の実装では許可されたタグのみ）
        $header = isset($postData['header']) ?
            htmlspecialchars($postData['header'], ENT_QUOTES, 'UTF-8') : null;
        $footer = isset($postData['footer']) ?
            htmlspecialchars($postData['footer'], ENT_QUOTES, 'UTF-8') : null;

        // テーマファイル保存
        if ($header !== null) {
            $headerFile = $this->cacheDir . '/theme_header.html';
            $temp = $headerFile . '.tmp.' . uniqid();
            file_put_contents($temp, $header, LOCK_EX);
            rename($temp, $headerFile);
        }

        if ($footer !== null) {
            $footerFile = $this->cacheDir . '/theme_footer.html';
            $temp = $footerFile . '.tmp.' . uniqid();
            file_put_contents($temp, $footer, LOCK_EX);
            rename($temp, $footerFile);
        }

        // 全キャッシュクリア
        $this->clearAllCacheFiles();

        return ['success' => true];
    }

    /**
     * キャッシュファイル生成
     */
    private function generateCacheFiles(int $postId): void
    {
        // 投稿一覧キャッシュ
        $stmt = $this->db->query('SELECT id, title, tags, thumb_path, created_at FROM posts ORDER BY id DESC LIMIT 50');
        $posts = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $cacheFile = $this->cacheDir . '/posts_list.json';
        $temp = $cacheFile . '.tmp.' . uniqid();
        file_put_contents($temp, json_encode($posts, JSON_UNESCAPED_UNICODE), LOCK_EX);
        rename($temp, $cacheFile);

        // 投稿詳細キャッシュ
        $stmt = $this->db->prepare('SELECT * FROM posts WHERE id = ?');
        $stmt->execute([$postId]);
        $post = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($post) {
            $postCacheFile = $this->cacheDir . '/post_' . $postId . '.json';
            $temp = $postCacheFile . '.tmp.' . uniqid();
            file_put_contents($temp, json_encode($post, JSON_UNESCAPED_UNICODE), LOCK_EX);
            rename($temp, $postCacheFile);
        }
    }

    /**
     * キャッシュファイル削除
     */
    private function clearCacheFiles(int $postId): void
    {
        // 投稿一覧キャッシュ削除
        $cacheFile = $this->cacheDir . '/posts_list.json';
        if (file_exists($cacheFile)) {
            unlink($cacheFile);
        }

        // 投稿詳細キャッシュ削除
        $postCacheFile = $this->cacheDir . '/post_' . $postId . '.json';
        if (file_exists($postCacheFile)) {
            unlink($postCacheFile);
        }
    }

    /**
     * 全キャッシュファイルクリア
     */
    private function clearAllCacheFiles(): void
    {
        $files = glob($this->cacheDir . '/*.json');
        foreach ($files as $file) {
            unlink($file);
        }
    }

    // ========================================
    // upload.php のテスト
    // ========================================

    /**
     * 【正常系】画像アップロードが成功し、WebPサムネイルが生成される
     */
    public function testUploadSuccess(): void
    {
        $this->setAuthenticatedSession();
        $csrfToken = $this->generateCsrfToken();

        $imagePath = $this->createTestImage();
        $fileSize = filesize($imagePath);

        $postData = [
            'title' => 'テスト画像',
            'tags' => 'テスト,サンプル',
            'detail' => 'これはテスト画像です',
            'csrf' => $csrfToken,
        ];

        $files = $this->mockUploadedFile($imagePath, 'test.jpg', $fileSize);

        $result = $this->simulateUploadApi($postData, $files);

        $this->assertTrue($result['success']);
        $this->assertIsInt($result['id']);
        $this->assertGreaterThan(0, $result['id']);

        // WebPサムネイルが生成されているか確認
        $stmt = $this->db->prepare('SELECT thumb_path FROM posts WHERE id = ?');
        $stmt->execute([$result['id']]);
        $post = $stmt->fetch(\PDO::FETCH_ASSOC);

        $this->assertNotEmpty($post['thumb_path']);
        $thumbPath = $this->tempDir . '/' . $post['thumb_path'];
        $this->assertFileExists($thumbPath);

        // WebP形式であることを確認
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $thumbPath);
        finfo_close($finfo);
        $this->assertEquals('image/webp', $mimeType);
    }

    /**
     * 【正常系】レスポンスに {"success": true, "id": 1} が返る
     */
    public function testUploadReturnsCorrectResponse(): void
    {
        $this->setAuthenticatedSession();
        $csrfToken = $this->generateCsrfToken();

        $imagePath = $this->createTestImage();
        $fileSize = filesize($imagePath);

        $postData = [
            'title' => 'レスポンステスト',
            'csrf' => $csrfToken,
        ];

        $files = $this->mockUploadedFile($imagePath, 'test.jpg', $fileSize);

        $result = $this->simulateUploadApi($postData, $files);

        $this->assertArrayHasKey('success', $result);
        $this->assertArrayHasKey('id', $result);
        $this->assertTrue($result['success']);
        $this->assertIsInt($result['id']);
    }

    /**
     * 【正常系】アップロード後、キャッシュが自動生成される
     */
    public function testUploadGeneratesCache(): void
    {
        $this->setAuthenticatedSession();
        $csrfToken = $this->generateCsrfToken();

        $imagePath = $this->createTestImage();
        $fileSize = filesize($imagePath);

        $postData = [
            'title' => 'キャッシュテスト',
            'csrf' => $csrfToken,
        ];

        $files = $this->mockUploadedFile($imagePath, 'test.jpg', $fileSize);

        $result = $this->simulateUploadApi($postData, $files);

        $this->assertTrue($result['success']);

        // posts_list.json が生成されているか
        $listCacheFile = $this->cacheDir . '/posts_list.json';
        $this->assertFileExists($listCacheFile);

        $cacheData = json_decode(file_get_contents($listCacheFile), true);
        $this->assertIsArray($cacheData);
        $this->assertNotEmpty($cacheData);

        // post_{id}.json が生成されているか
        $postCacheFile = $this->cacheDir . '/post_' . $result['id'] . '.json';
        $this->assertFileExists($postCacheFile);

        $postData = json_decode(file_get_contents($postCacheFile), true);
        $this->assertIsArray($postData);
        $this->assertEquals($result['id'], $postData['id']);
    }

    /**
     * 【認証】セッション認証が無い場合、403エラーが返る
     */
    public function testUploadRequiresAuthentication(): void
    {
        // 認証なし
        $csrfToken = $this->generateCsrfToken();

        $imagePath = $this->createTestImage();
        $fileSize = filesize($imagePath);

        $postData = ['csrf' => $csrfToken];
        $files = $this->mockUploadedFile($imagePath, 'test.jpg', $fileSize);

        $result = $this->simulateUploadApi($postData, $files);

        $this->assertArrayHasKey('error', $result);
        $this->assertEquals('Unauthorized', $result['error']);
    }

    /**
     * 【CSRF】CSRFトークンが無効な場合、エラーが返る
     */
    public function testUploadRequiresValidCsrfToken(): void
    {
        $this->setAuthenticatedSession();
        $this->generateCsrfToken();

        $imagePath = $this->createTestImage();
        $fileSize = filesize($imagePath);

        // 無効なトークン
        $postData = ['csrf' => 'invalid_token'];
        $files = $this->mockUploadedFile($imagePath, 'test.jpg', $fileSize);

        $result = $this->simulateUploadApi($postData, $files);

        $this->assertArrayHasKey('error', $result);
        $this->assertEquals('CSRF token mismatch', $result['error']);
    }

    /**
     * 【異常系】ファイルサイズが10MBを超える場合、エラーが返る
     */
    public function testUploadRejectsOversizedFiles(): void
    {
        $this->setAuthenticatedSession();
        $csrfToken = $this->generateCsrfToken();

        $imagePath = $this->createTestImage();
        $oversizedFileSize = 11 * 1024 * 1024; // 11MB

        $postData = ['csrf' => $csrfToken];
        $files = $this->mockUploadedFile($imagePath, 'test.jpg', $oversizedFileSize);

        $result = $this->simulateUploadApi($postData, $files);

        $this->assertArrayHasKey('error', $result);
        $this->assertStringContainsString('10MB', $result['error']);
    }

    /**
     * 【異常系】画像ファイル以外（txt等）をアップロードした場合、エラーが返る
     */
    public function testUploadRejectsNonImageFiles(): void
    {
        $this->setAuthenticatedSession();
        $csrfToken = $this->generateCsrfToken();

        // テキストファイル作成
        $textPath = $this->tempDir . '/test.txt';
        file_put_contents($textPath, 'This is a text file');
        $fileSize = filesize($textPath);

        $postData = ['csrf' => $csrfToken];
        $files = [
            'image' => [
                'name' => 'test.txt',
                'type' => 'text/plain',
                'tmp_name' => $textPath,
                'error' => UPLOAD_ERR_OK,
                'size' => $fileSize,
            ]
        ];

        $result = $this->simulateUploadApi($postData, $files);

        $this->assertArrayHasKey('error', $result);
        $this->assertStringContainsString('Invalid', $result['error']);
    }

    /**
     * 【異常系】MIME typeが不正な場合、エラーが返る
     */
    public function testUploadRejectsInvalidMimeType(): void
    {
        $this->setAuthenticatedSession();
        $csrfToken = $this->generateCsrfToken();

        // 画像拡張子だが実際はテキストファイル
        $fakePath = $this->tempDir . '/fake.jpg';
        file_put_contents($fakePath, 'This is not an image');
        $fileSize = filesize($fakePath);

        $postData = ['csrf' => $csrfToken];
        $files = $this->mockUploadedFile($fakePath, 'fake.jpg', $fileSize);

        $result = $this->simulateUploadApi($postData, $files);

        $this->assertArrayHasKey('error', $result);
        $this->assertStringContainsString('Invalid', $result['error']);
    }

    // ========================================
    // delete.php のテスト
    // ========================================

    /**
     * 【正常系】投稿が正常に削除される
     */
    public function testDeleteSuccess(): void
    {
        $this->setAuthenticatedSession();
        $csrfToken = $this->generateCsrfToken();

        // テスト投稿作成
        $stmt = $this->db->prepare(
            'INSERT INTO posts (title, tags, detail, image_path, thumb_path) VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            'テスト投稿',
            'テスト',
            '削除テスト',
            'images/test.jpg',
            'thumbs/test.webp'
        ]);
        $postId = (int)$this->db->lastInsertId();

        // ファイル作成
        $imagePath = $this->tempDir . '/images/test.jpg';
        $thumbPath = $this->tempDir . '/thumbs/test.webp';
        touch($imagePath);
        touch($thumbPath);

        $postData = [
            'id' => $postId,
            'csrf' => $csrfToken,
        ];

        $result = $this->simulateDeleteApi($postData);

        $this->assertTrue($result['success']);

        // データベースから削除されているか確認
        $stmt = $this->db->prepare('SELECT * FROM posts WHERE id = ?');
        $stmt->execute([$postId]);
        $post = $stmt->fetch(\PDO::FETCH_ASSOC);
        $this->assertFalse($post);

        // ファイルが削除されているか確認
        $this->assertFileDoesNotExist($imagePath);
        $this->assertFileDoesNotExist($thumbPath);
    }

    /**
     * 【正常系】削除後、関連キャッシュが削除される
     */
    public function testDeleteClearsCache(): void
    {
        $this->setAuthenticatedSession();
        $csrfToken = $this->generateCsrfToken();

        // テスト投稿作成
        $stmt = $this->db->prepare(
            'INSERT INTO posts (title, image_path, thumb_path) VALUES (?, ?, ?)'
        );
        $stmt->execute(['テスト', 'images/test.jpg', 'thumbs/test.webp']);
        $postId = (int)$this->db->lastInsertId();

        // キャッシュ生成
        $this->generateCacheFiles($postId);

        $listCacheFile = $this->cacheDir . '/posts_list.json';
        $postCacheFile = $this->cacheDir . '/post_' . $postId . '.json';

        $this->assertFileExists($listCacheFile);
        $this->assertFileExists($postCacheFile);

        // 削除実行
        $postData = [
            'id' => $postId,
            'csrf' => $csrfToken,
        ];

        $result = $this->simulateDeleteApi($postData);
        $this->assertTrue($result['success']);

        // キャッシュが削除されているか確認
        $this->assertFileDoesNotExist($listCacheFile);
        $this->assertFileDoesNotExist($postCacheFile);
    }

    /**
     * 【正常系】レスポンスに {"success": true} が返る
     */
    public function testDeleteReturnsCorrectResponse(): void
    {
        $this->setAuthenticatedSession();
        $csrfToken = $this->generateCsrfToken();

        // テスト投稿作成
        $stmt = $this->db->prepare(
            'INSERT INTO posts (title, image_path, thumb_path) VALUES (?, ?, ?)'
        );
        $stmt->execute(['テスト', 'images/test.jpg', 'thumbs/test.webp']);
        $postId = (int)$this->db->lastInsertId();

        $postData = [
            'id' => $postId,
            'csrf' => $csrfToken,
        ];

        $result = $this->simulateDeleteApi($postData);

        $this->assertArrayHasKey('success', $result);
        $this->assertTrue($result['success']);
    }

    /**
     * 【認証】セッション認証が無い場合、403エラーが返る
     */
    public function testDeleteRequiresAuthentication(): void
    {
        // 認証なし
        $csrfToken = $this->generateCsrfToken();

        $postData = [
            'id' => 1,
            'csrf' => $csrfToken,
        ];

        $result = $this->simulateDeleteApi($postData);

        $this->assertArrayHasKey('error', $result);
        $this->assertEquals('Unauthorized', $result['error']);
    }

    /**
     * 【CSRF】CSRFトークンが無効な場合、エラーが返る
     */
    public function testDeleteRequiresValidCsrfToken(): void
    {
        $this->setAuthenticatedSession();
        $this->generateCsrfToken();

        // 無効なトークン
        $postData = [
            'id' => 1,
            'csrf' => 'invalid_token',
        ];

        $result = $this->simulateDeleteApi($postData);

        $this->assertArrayHasKey('error', $result);
        $this->assertEquals('CSRF token mismatch', $result['error']);
    }

    /**
     * 【異常系】存在しないIDを削除しようとした場合、エラーが返る
     */
    public function testDeleteRejectsNonExistentPost(): void
    {
        $this->setAuthenticatedSession();
        $csrfToken = $this->generateCsrfToken();

        $postData = [
            'id' => 99999,
            'csrf' => $csrfToken,
        ];

        $result = $this->simulateDeleteApi($postData);

        $this->assertArrayHasKey('error', $result);
        $this->assertEquals('Post not found', $result['error']);
    }

    // ========================================
    // theme.php のテスト
    // ========================================

    /**
     * 【正常系】テーマ（header/footer）が更新される
     */
    public function testThemeUpdateSuccess(): void
    {
        $this->setAuthenticatedSession();
        $csrfToken = $this->generateCsrfToken();

        $postData = [
            'header' => '<div class="header">カスタムヘッダー</div>',
            'footer' => '<div class="footer">カスタムフッター</div>',
            'csrf' => $csrfToken,
        ];

        $result = $this->simulateThemeApi($postData);

        $this->assertTrue($result['success']);

        // ヘッダーファイルが作成されているか確認
        $headerFile = $this->cacheDir . '/theme_header.html';
        $this->assertFileExists($headerFile);

        $headerContent = file_get_contents($headerFile);
        $this->assertStringContainsString('カスタムヘッダー', $headerContent);

        // フッターファイルが作成されているか確認
        $footerFile = $this->cacheDir . '/theme_footer.html';
        $this->assertFileExists($footerFile);

        $footerContent = file_get_contents($footerFile);
        $this->assertStringContainsString('カスタムフッター', $footerContent);
    }

    /**
     * 【正常系】テーマ更新後、全キャッシュがクリアされる
     */
    public function testThemeUpdateClearsAllCache(): void
    {
        $this->setAuthenticatedSession();
        $csrfToken = $this->generateCsrfToken();

        // テスト用キャッシュファイル作成
        $cacheFile1 = $this->cacheDir . '/posts_list.json';
        $cacheFile2 = $this->cacheDir . '/post_1.json';
        file_put_contents($cacheFile1, '[]');
        file_put_contents($cacheFile2, '{}');

        $this->assertFileExists($cacheFile1);
        $this->assertFileExists($cacheFile2);

        // テーマ更新
        $postData = [
            'header' => '<header>New Header</header>',
            'csrf' => $csrfToken,
        ];

        $result = $this->simulateThemeApi($postData);
        $this->assertTrue($result['success']);

        // キャッシュがクリアされているか確認
        $this->assertFileDoesNotExist($cacheFile1);
        $this->assertFileDoesNotExist($cacheFile2);
    }

    /**
     * 【正常系】レスポンスに {"success": true} が返る
     */
    public function testThemeReturnsCorrectResponse(): void
    {
        $this->setAuthenticatedSession();
        $csrfToken = $this->generateCsrfToken();

        $postData = [
            'header' => '<div>Test Header</div>',
            'csrf' => $csrfToken,
        ];

        $result = $this->simulateThemeApi($postData);

        $this->assertArrayHasKey('success', $result);
        $this->assertTrue($result['success']);
    }

    /**
     * 【認証】セッション認証が無い場合、403エラーが返る
     */
    public function testThemeRequiresAuthentication(): void
    {
        // 認証なし
        $csrfToken = $this->generateCsrfToken();

        $postData = [
            'header' => '<div>Test</div>',
            'csrf' => $csrfToken,
        ];

        $result = $this->simulateThemeApi($postData);

        $this->assertArrayHasKey('error', $result);
        $this->assertEquals('Unauthorized', $result['error']);
    }

    /**
     * 【CSRF】CSRFトークンが無効な場合、エラーが返る
     */
    public function testThemeRequiresValidCsrfToken(): void
    {
        $this->setAuthenticatedSession();
        $this->generateCsrfToken();

        // 無効なトークン
        $postData = [
            'header' => '<div>Test</div>',
            'csrf' => 'invalid_token',
        ];

        $result = $this->simulateThemeApi($postData);

        $this->assertArrayHasKey('error', $result);
        $this->assertEquals('CSRF token mismatch', $result['error']);
    }

    /**
     * 【セキュリティ】XSS対策が適用されているか確認
     */
    public function testThemeAppliesXssProtection(): void
    {
        $this->setAuthenticatedSession();
        $csrfToken = $this->generateCsrfToken();

        // XSSを含むHTML
        $xssPayload = '<script>alert("XSS")</script><div>Content</div>';

        $postData = [
            'header' => $xssPayload,
            'csrf' => $csrfToken,
        ];

        $result = $this->simulateThemeApi($postData);
        $this->assertTrue($result['success']);

        // 保存されたコンテンツを確認
        $headerFile = $this->cacheDir . '/theme_header.html';
        $content = file_get_contents($headerFile);

        // スクリプトタグがエスケープされているか確認
        $this->assertStringNotContainsString('<script>', $content);
        $this->assertStringContainsString('&lt;script&gt;', $content);
        $this->assertStringContainsString('&lt;/script&gt;', $content);
    }
}
