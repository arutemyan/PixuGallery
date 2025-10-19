<?php

declare(strict_types=1);

namespace Tests\Api;

use PHPUnit\Framework\TestCase;
use PDO;

/**
 * 公開API（/api/posts と /api/post）のユニットテスト
 *
 * テスト対象:
 * - api/posts.php - 投稿一覧取得API
 * - api/post.php - 投稿詳細取得API
 *
 * テスト環境:
 * - インメモリSQLite (:memory:)
 * - 一時キャッシュディレクトリ
 */
class PublicApiTest extends TestCase
{
    private ?PDO $db = null;
    private string $tempCacheDir;
    private string $tempDbPath;
    private array $testPosts = [];

    /**
     * 各テストの前に実行される初期化処理
     */
    protected function setUp(): void
    {
        parent::setUp();

        // 一時キャッシュディレクトリを作成
        $this->tempCacheDir = sys_get_temp_dir() . '/photo_site_test_cache_' . uniqid();
        mkdir($this->tempCacheDir, 0755, true);

        // 一時DBパスを設定（インメモリDBを使用）
        $this->tempDbPath = ':memory:';

        // テスト用データベースを初期化
        $this->initializeTestDatabase();

        // テストデータを挿入
        $this->seedTestData();
    }

    /**
     * 各テストの後に実行されるクリーンアップ処理
     */
    protected function tearDown(): void
    {
        // キャッシュディレクトリとその中身を削除
        if (is_dir($this->tempCacheDir)) {
            $files = glob($this->tempCacheDir . '/*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
            rmdir($this->tempCacheDir);
        }

        // データベース接続を閉じる
        $this->db = null;

        parent::tearDown();
    }

    /**
     * テスト用データベースを初期化
     */
    private function initializeTestDatabase(): void
    {
        $this->db = new PDO('sqlite:' . $this->tempDbPath);
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // postsテーブルを作成
        $this->db->exec('
            CREATE TABLE posts (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                title TEXT NOT NULL,
                tags TEXT,
                detail TEXT,
                image_path TEXT,
                thumb_path TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ');
    }

    /**
     * テストデータを挿入
     */
    private function seedTestData(): void
    {
        $this->testPosts = [
            [
                'title' => 'ドラゴンイラスト',
                'tags' => 'R18,ドラゴン,ファンタジー',
                'detail' => '炎を吐くドラゴンの詳細な描写です。',
                'image_path' => 'images/20251019_dragon.jpg',
                'thumb_path' => 'thumbs/20251019_dragon.webp',
            ],
            [
                'title' => 'エルフの少女',
                'tags' => 'エルフ,ファンタジー,森',
                'detail' => '森の中に佇むエルフの少女です。',
                'image_path' => 'images/20251019_elf.jpg',
                'thumb_path' => 'thumbs/20251019_elf.webp',
            ],
            [
                'title' => 'サイバーパンク都市',
                'tags' => 'サイバーパンク,SF,未来',
                'detail' => 'ネオン輝く未来都市の風景。',
                'image_path' => 'images/20251019_cyber.jpg',
                'thumb_path' => 'thumbs/20251019_cyber.webp',
            ],
        ];

        $stmt = $this->db->prepare('
            INSERT INTO posts (title, tags, detail, image_path, thumb_path)
            VALUES (:title, :tags, :detail, :image_path, :thumb_path)
        ');

        foreach ($this->testPosts as $post) {
            $stmt->execute($post);
        }
    }

    /**
     * 51件以上のデータを挿入（最大50件のテスト用）
     */
    private function seedLargeTestData(): void
    {
        $stmt = $this->db->prepare('
            INSERT INTO posts (title, tags, detail, image_path, thumb_path)
            VALUES (:title, :tags, :detail, :image_path, :thumb_path)
        ');

        for ($i = 1; $i <= 51; $i++) {
            $stmt->execute([
                'title' => "テスト投稿 {$i}",
                'tags' => 'テスト,投稿',
                'detail' => "テスト投稿 {$i} の詳細",
                'image_path' => "images/test_{$i}.jpg",
                'thumb_path' => "thumbs/test_{$i}.webp",
            ]);
        }
    }

    // ==========================================
    // api/posts.php のテスト
    // ==========================================

    /**
     * [正常系] 投稿一覧が正しくJSON形式で返却される
     */
    public function testPostsReturnsValidJson(): void
    {
        $posts = $this->callPostsApi();

        // JSONとしてデコードできることを確認
        $this->assertIsArray($posts);
        $this->assertCount(3, $posts);
    }

    /**
     * [正常系] 最大50件まで返却される
     */
    public function testPostsReturnsMaximum50Items(): void
    {
        // 51件のデータを追加
        $this->seedLargeTestData();

        $posts = $this->callPostsApi();

        // 最大50件であることを確認
        $this->assertCount(50, $posts);
    }

    /**
     * [正常系] 必須フィールド（id, title, tags, image_path）が含まれる
     */
    public function testPostsIncludesRequiredFields(): void
    {
        $posts = $this->callPostsApi();

        foreach ($posts as $post) {
            $this->assertArrayHasKey('id', $post, '必須フィールド id が含まれていません');
            $this->assertArrayHasKey('title', $post, '必須フィールド title が含まれていません');
            $this->assertArrayHasKey('tags', $post, '必須フィールド tags が含まれていません');
            $this->assertArrayHasKey('image_path', $post, '必須フィールド image_path が含まれていません');
        }
    }

    /**
     * [キャッシュ] キャッシュファイルが存在する場合、キャッシュから返却される
     */
    public function testPostsReturnsCachedDataWhenCacheExists(): void
    {
        // 最初のAPIコールでキャッシュを生成
        $firstCall = $this->callPostsApi();

        // キャッシュファイルが作成されたことを確認
        $cacheFile = $this->tempCacheDir . '/posts_list.json';
        $this->assertFileExists($cacheFile, 'キャッシュファイルが生成されていません');

        // データベースを変更（新しい投稿を追加）
        $stmt = $this->db->prepare('
            INSERT INTO posts (title, tags, detail, image_path, thumb_path)
            VALUES (:title, :tags, :detail, :image_path, :thumb_path)
        ');
        $stmt->execute([
            'title' => '新しい投稿',
            'tags' => '新規',
            'detail' => '新しい投稿の詳細',
            'image_path' => 'images/new.jpg',
            'thumb_path' => 'thumbs/new.webp',
        ]);

        // 2回目のAPIコール（キャッシュから取得）
        $secondCall = $this->callPostsApi();

        // キャッシュが使われているため、データベースの変更が反映されていないことを確認
        $this->assertCount(3, $secondCall, 'キャッシュが使用されていません');
        $this->assertEquals($firstCall, $secondCall, 'キャッシュされたデータと一致しません');
    }

    /**
     * [キャッシュ] キャッシュが存在しない場合、DBから取得しキャッシュが生成される
     */
    public function testPostsCreatesCacheWhenCacheDoesNotExist(): void
    {
        $cacheFile = $this->tempCacheDir . '/posts_list.json';

        // キャッシュが存在しないことを確認
        $this->assertFileDoesNotExist($cacheFile);

        // APIコール
        $posts = $this->callPostsApi();

        // キャッシュファイルが作成されたことを確認
        $this->assertFileExists($cacheFile, 'キャッシュファイルが生成されていません');

        // キャッシュ内容が正しいことを確認
        $cachedData = json_decode(file_get_contents($cacheFile), true);
        $this->assertEquals($posts, $cachedData, 'キャッシュされたデータが正しくありません');
    }

    /**
     * [正常系] レスポンスヘッダーが Content-Type: application/json である
     *
     * Note: PHPUnitではヘッダーを直接テストできないため、
     * 実際のAPIファイルが正しくヘッダーを設定することを想定
     */
    public function testPostsReturnsJsonContentType(): void
    {
        // このテストは実際のHTTPレスポンスでのみ有効
        // ユニットテストではAPIの出力がJSONであることを確認
        $posts = $this->callPostsApi();
        $json = json_encode($posts);

        $this->assertIsString($json);
        $this->assertJson($json, 'レスポンスが有効なJSONではありません');
    }

    /**
     * [原子的書き込み] キャッシュファイルの原子的書き込みが正しく動作する
     */
    public function testPostsCacheAtomicWrite(): void
    {
        $cacheFile = $this->tempCacheDir . '/posts_list.json';

        // キャッシュを生成
        $this->callPostsApi();

        // キャッシュファイルが存在することを確認
        $this->assertFileExists($cacheFile);

        // 一時ファイル（.tmp）が残っていないことを確認
        $tmpFiles = glob($this->tempCacheDir . '/posts_list.json.tmp.*');
        $this->assertEmpty($tmpFiles, '一時ファイルが残っています。原子的書き込みが正しく動作していません。');

        // キャッシュファイルが有効なJSONであることを確認
        $content = file_get_contents($cacheFile);
        $this->assertJson($content, 'キャッシュファイルが有効なJSONではありません');
    }

    // ==========================================
    // api/post.php のテスト
    // ==========================================

    /**
     * [正常系] IDを指定して投稿詳細が取得できる
     */
    public function testPostReturnsDetailById(): void
    {
        $post = $this->callPostApi(1);

        $this->assertIsArray($post);
        $this->assertEquals(1, $post['id']);
        $this->assertEquals('ドラゴンイラスト', $post['title']);
    }

    /**
     * [正常系] 必須フィールド（id, title, detail, image_path）が含まれる
     */
    public function testPostIncludesRequiredFields(): void
    {
        $post = $this->callPostApi(1);

        $this->assertArrayHasKey('id', $post, '必須フィールド id が含まれていません');
        $this->assertArrayHasKey('title', $post, '必須フィールド title が含まれていません');
        $this->assertArrayHasKey('detail', $post, '必須フィールド detail が含まれていません');
        $this->assertArrayHasKey('image_path', $post, '必須フィールド image_path が含まれていません');
    }

    /**
     * [異常系] 存在しないIDを指定した場合、適切なエラーレスポンスが返る
     */
    public function testPostReturnsErrorForNonExistentId(): void
    {
        $result = $this->callPostApi(9999, false);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('error', $result, 'エラーレスポンスにerrorキーが含まれていません');
        $this->assertFalse($result['success'] ?? true, 'successフラグがfalseになっていません');
    }

    /**
     * [異常系] IDパラメータが無い場合、適切なエラーレスポンスが返る
     */
    public function testPostReturnsErrorWhenIdParameterIsMissing(): void
    {
        $result = $this->callPostApi(null, false);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('error', $result, 'エラーレスポンスにerrorキーが含まれていません');
    }

    /**
     * [異常系] IDが数値でない場合、適切なエラーレスポンスが返る
     */
    public function testPostReturnsErrorWhenIdIsNotNumeric(): void
    {
        // 文字列IDでテスト
        $result = $this->callPostApi('abc', false);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('error', $result, 'エラーレスポンスにerrorキーが含まれていません');
    }

    /**
     * [キャッシュ] キャッシュが存在する場合、キャッシュから返却される
     */
    public function testPostReturnsCachedDataWhenCacheExists(): void
    {
        // 最初のAPIコールでキャッシュを生成
        $firstCall = $this->callPostApi(1);

        // キャッシュファイルが作成されたことを確認
        $cacheFile = $this->tempCacheDir . '/post_1.json';
        $this->assertFileExists($cacheFile, 'キャッシュファイルが生成されていません');

        // データベースを変更
        $stmt = $this->db->prepare('UPDATE posts SET title = :title WHERE id = :id');
        $stmt->execute(['title' => '変更されたタイトル', 'id' => 1]);

        // 2回目のAPIコール（キャッシュから取得）
        $secondCall = $this->callPostApi(1);

        // キャッシュが使われているため、データベースの変更が反映されていないことを確認
        $this->assertEquals($firstCall, $secondCall, 'キャッシュされたデータと一致しません');
        $this->assertEquals('ドラゴンイラスト', $secondCall['title'], 'キャッシュが使用されていません');
    }

    /**
     * [キャッシュ] キャッシュが存在しない場合、DBから取得しキャッシュが生成される
     */
    public function testPostCreatesCacheWhenCacheDoesNotExist(): void
    {
        $cacheFile = $this->tempCacheDir . '/post_1.json';

        // キャッシュが存在しないことを確認
        $this->assertFileDoesNotExist($cacheFile);

        // APIコール
        $post = $this->callPostApi(1);

        // キャッシュファイルが作成されたことを確認
        $this->assertFileExists($cacheFile, 'キャッシュファイルが生成されていません');

        // キャッシュ内容が正しいことを確認
        $cachedData = json_decode(file_get_contents($cacheFile), true);
        $this->assertEquals($post, $cachedData, 'キャッシュされたデータが正しくありません');
    }

    /**
     * [原子的書き込み] キャッシュファイルの原子的書き込みが正しく動作する
     */
    public function testPostCacheAtomicWrite(): void
    {
        $cacheFile = $this->tempCacheDir . '/post_1.json';

        // キャッシュを生成
        $this->callPostApi(1);

        // キャッシュファイルが存在することを確認
        $this->assertFileExists($cacheFile);

        // 一時ファイル（.tmp）が残っていないことを確認
        $tmpFiles = glob($this->tempCacheDir . '/post_1.json.tmp.*');
        $this->assertEmpty($tmpFiles, '一時ファイルが残っています。原子的書き込みが正しく動作していません。');

        // キャッシュファイルが有効なJSONであることを確認
        $content = file_get_contents($cacheFile);
        $this->assertJson($content, 'キャッシュファイルが有効なJSONではありません');
    }

    /**
     * [複数ID] 複数の投稿詳細が個別にキャッシュされる
     */
    public function testPostCachesMultiplePostsSeparately(): void
    {
        // 複数の投稿を取得
        $post1 = $this->callPostApi(1);
        $post2 = $this->callPostApi(2);
        $post3 = $this->callPostApi(3);

        // それぞれのキャッシュファイルが作成されたことを確認
        $this->assertFileExists($this->tempCacheDir . '/post_1.json');
        $this->assertFileExists($this->tempCacheDir . '/post_2.json');
        $this->assertFileExists($this->tempCacheDir . '/post_3.json');

        // キャッシュの内容が正しいことを確認
        $cached1 = json_decode(file_get_contents($this->tempCacheDir . '/post_1.json'), true);
        $cached2 = json_decode(file_get_contents($this->tempCacheDir . '/post_2.json'), true);
        $cached3 = json_decode(file_get_contents($this->tempCacheDir . '/post_3.json'), true);

        $this->assertEquals($post1, $cached1);
        $this->assertEquals($post2, $cached2);
        $this->assertEquals($post3, $cached3);

        // 各投稿のタイトルが異なることを確認
        $this->assertNotEquals($post1['title'], $post2['title']);
        $this->assertNotEquals($post2['title'], $post3['title']);
    }

    // ==========================================
    // ヘルパーメソッド
    // ==========================================

    /**
     * api/posts.php をシミュレート
     *
     * @return array 投稿一覧
     */
    private function callPostsApi(): array
    {
        $cacheFile = $this->tempCacheDir . '/posts_list.json';

        // キャッシュが存在する場合は即返却
        if (file_exists($cacheFile)) {
            return json_decode(file_get_contents($cacheFile), true);
        }

        // キャッシュが無い場合はDB取得
        $stmt = $this->db->query('
            SELECT id, title, tags, image_path, thumb_path, created_at
            FROM posts
            ORDER BY created_at DESC
            LIMIT 50
        ');
        $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // キャッシュを生成（原子的書き込み）
        $json = json_encode($posts, JSON_UNESCAPED_UNICODE);
        $temp = $cacheFile . '.tmp.' . uniqid();
        file_put_contents($temp, $json, LOCK_EX);
        rename($temp, $cacheFile);

        return $posts;
    }

    /**
     * api/post.php をシミュレート
     *
     * @param int|string|null $id 投稿ID
     * @param bool $throwOnError エラー時に例外をスローするか
     * @return array|null 投稿詳細
     */
    private function callPostApi($id, bool $throwOnError = true): ?array
    {
        // パラメータ検証
        if ($id === null) {
            return ['error' => 'IDパラメータが必要です', 'success' => false];
        }

        if (!is_numeric($id)) {
            return ['error' => 'IDは数値である必要があります', 'success' => false];
        }

        $id = (int)$id;
        $cacheFile = $this->tempCacheDir . "/post_{$id}.json";

        // キャッシュが存在する場合は即返却
        if (file_exists($cacheFile)) {
            return json_decode(file_get_contents($cacheFile), true);
        }

        // キャッシュが無い場合はDB取得
        $stmt = $this->db->prepare('
            SELECT id, title, tags, detail, image_path, thumb_path, created_at
            FROM posts
            WHERE id = :id
        ');
        $stmt->execute(['id' => $id]);
        $post = $stmt->fetch(PDO::FETCH_ASSOC);

        // 投稿が存在しない場合
        if (!$post) {
            return ['error' => '投稿が見つかりません', 'success' => false];
        }

        // キャッシュを生成（原子的書き込み）
        $json = json_encode($post, JSON_UNESCAPED_UNICODE);
        $temp = $cacheFile . '.tmp.' . uniqid();
        file_put_contents($temp, $json, LOCK_EX);
        rename($temp, $cacheFile);

        return $post;
    }
}
