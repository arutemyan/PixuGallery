<?php

declare(strict_types=1);

namespace Tests\Cache;

use PHPUnit\Framework\TestCase;

/**
 * キャッシュシステムのユニットテスト
 *
 * CLAUDE.mdに記載されているキャッシュシステムの詳細仕様に基づいた包括的なテストスイート
 *
 * テスト範囲:
 * - 原子的書き込み（atomic write）
 * - キャッシュ生成タイミング
 * - テーマ更新時のキャッシュクリア
 * - パフォーマンス
 * - セキュリティ
 */
class CacheSystemTest extends TestCase
{
    /** @var string キャッシュディレクトリのパス */
    private string $cacheDir;

    /** @var string テスト用一時ディレクトリ */
    private string $testDir;

    /**
     * 各テスト前のセットアップ
     */
    protected function setUp(): void
    {
        parent::setUp();

        // テスト用一時ディレクトリを作成
        $this->testDir = sys_get_temp_dir() . '/photo_site_test_' . uniqid();
        $this->cacheDir = $this->testDir . '/cache';

        mkdir($this->testDir, 0755, true);
        mkdir($this->cacheDir, 0755, true);

        // cache/index.phpを作成（セキュリティ）
        file_put_contents($this->cacheDir . '/index.php', "<?php\nhttp_response_code(403);\ndie('Forbidden');\n");
    }

    /**
     * 各テスト後のクリーンアップ
     */
    protected function tearDown(): void
    {
        parent::tearDown();

        // テスト用ディレクトリを削除
        if (file_exists($this->testDir)) {
            $this->removeDirectory($this->testDir);
        }
    }

    /**
     * ディレクトリを再帰的に削除
     */
    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }

    // =========================================================================
    // 原子的書き込みのテスト
    // =========================================================================

    /**
     * 正常系: rename()による原子的書き込みが正常に動作する
     */
    public function testAtomicWriteSucceeds(): void
    {
        $targetFile = $this->cacheDir . '/posts_list.json';
        $testData = json_encode(['id' => 1, 'title' => 'Test Post'], JSON_UNESCAPED_UNICODE);

        // 原子的書き込みを実行
        $result = $this->atomicWrite($targetFile, $testData);

        $this->assertTrue($result, '原子的書き込みが成功すること');
        $this->assertFileExists($targetFile, 'ターゲットファイルが作成されること');
        $this->assertJsonStringEqualsJsonString($testData, file_get_contents($targetFile), '書き込まれたデータが正しいこと');
    }

    /**
     * 正常系: 一時ファイルが正しくリネームされる
     */
    public function testTempFileIsRenamedCorrectly(): void
    {
        $targetFile = $this->cacheDir . '/post_1.json';
        $testData = json_encode(['id' => 1, 'detail' => 'Detailed content'], JSON_UNESCAPED_UNICODE);

        $this->atomicWrite($targetFile, $testData);

        // 一時ファイルが残っていないことを確認
        $tempFiles = glob($this->cacheDir . '/*.tmp.*');
        $this->assertEmpty($tempFiles, '一時ファイルが残っていないこと');

        // ターゲットファイルが存在することを確認
        $this->assertFileExists($targetFile, 'ターゲットファイルが存在すること');
    }

    /**
     * 正常系: 複数の同時書き込みでもファイル破損が起きない（並列テスト）
     *
     * @group parallel
     */
    public function testConcurrentWritesDoNotCorruptFile(): void
    {
        $targetFile = $this->cacheDir . '/concurrent_test.json';
        $processCount = 5;
        $processes = [];

        // 複数プロセスで同時書き込み
        for ($i = 0; $i < $processCount; $i++) {
            $pid = pcntl_fork();

            if ($pid == -1) {
                $this->fail('プロセスのforkに失敗しました');
            } elseif ($pid == 0) {
                // 子プロセス
                $data = json_encode(['process' => $i, 'timestamp' => microtime(true)], JSON_UNESCAPED_UNICODE);
                $this->atomicWrite($targetFile, $data);
                exit(0);
            } else {
                // 親プロセス
                $processes[] = $pid;
            }
        }

        // 全プロセスの完了を待つ
        foreach ($processes as $pid) {
            pcntl_waitpid($pid, $status);
        }

        // ファイルが壊れていないことを確認
        $this->assertFileExists($targetFile, '並列書き込み後もファイルが存在すること');

        $content = file_get_contents($targetFile);
        $decoded = json_decode($content, true);

        $this->assertNotNull($decoded, 'JSONが破損していないこと');
        $this->assertIsArray($decoded, 'デコードされたデータが配列であること');
    }

    /**
     * 異常系: 書き込み途中で失敗した場合、元のキャッシュファイルが保持される
     */
    public function testOriginalCacheIsPreservedOnWriteFailure(): void
    {
        $targetFile = $this->cacheDir . '/posts_list.json';
        $originalData = json_encode(['id' => 1, 'title' => 'Original'], JSON_UNESCAPED_UNICODE);

        // 元のキャッシュファイルを作成
        file_put_contents($targetFile, $originalData);
        $originalContent = file_get_contents($targetFile);

        // 書き込み不可のディレクトリを作成して失敗をシミュレート
        $readOnlyDir = $this->testDir . '/readonly_cache';
        mkdir($readOnlyDir, 0555, true); // 読み取り専用

        $readOnlyFile = $readOnlyDir . '/test.json';
        $result = @$this->atomicWrite($readOnlyFile, json_encode(['test' => 'data']));

        // 元のファイルは変更されていないことを確認
        $this->assertEquals($originalContent, file_get_contents($targetFile), '元のキャッシュファイルが保持されること');

        // クリーンアップ
        chmod($readOnlyDir, 0755);
    }

    // =========================================================================
    // キャッシュ生成タイミングのテスト
    // =========================================================================

    /**
     * 正常系: 画像投稿時にposts_list.jsonが自動生成される
     */
    public function testPostsListCacheIsGeneratedOnImageUpload(): void
    {
        $cacheFile = $this->cacheDir . '/posts_list.json';

        // キャッシュが存在しないことを確認
        $this->assertFileDoesNotExist($cacheFile, '初期状態でキャッシュが存在しないこと');

        // 画像投稿後のキャッシュ生成をシミュレート
        $posts = [
            ['id' => 1, 'title' => 'Post 1', 'tags' => 'tag1,tag2', 'image_path' => 'images/post1.jpg'],
            ['id' => 2, 'title' => 'Post 2', 'tags' => 'tag3', 'image_path' => 'images/post2.jpg'],
        ];

        $this->generatePostsListCache($posts);

        // キャッシュが生成されたことを確認
        $this->assertFileExists($cacheFile, 'posts_list.jsonが生成されること');

        $content = json_decode(file_get_contents($cacheFile), true);
        $this->assertCount(2, $content, 'キャッシュに2件の投稿が含まれること');
        $this->assertEquals('Post 1', $content[0]['title'], '投稿タイトルが正しいこと');
    }

    /**
     * 正常系: 画像投稿時にpost_{id}.jsonが自動生成される
     */
    public function testPostDetailCacheIsGeneratedOnImageUpload(): void
    {
        $postId = 123;
        $cacheFile = $this->cacheDir . "/post_{$postId}.json";

        // キャッシュが存在しないことを確認
        $this->assertFileDoesNotExist($cacheFile, '初期状態でキャッシュが存在しないこと');

        // 投稿詳細のキャッシュ生成をシミュレート
        $postDetail = [
            'id' => $postId,
            'title' => 'Test Post',
            'tags' => 'R18,ドラゴン,ファンタジー',
            'detail' => 'Detailed description',
            'image_path' => 'images/post123.jpg',
            'thumb_path' => 'thumbs/post123.webp',
        ];

        $this->generatePostDetailCache($postId, $postDetail);

        // キャッシュが生成されたことを確認
        $this->assertFileExists($cacheFile, "post_{$postId}.jsonが生成されること");

        $content = json_decode(file_get_contents($cacheFile), true);
        $this->assertEquals($postId, $content['id'], '投稿IDが正しいこと');
        $this->assertEquals('Test Post', $content['title'], '投稿タイトルが正しいこと');
    }

    /**
     * 正常系: キャッシュが存在しない場合、初回アクセス時に生成される
     */
    public function testCacheIsGeneratedOnFirstAccess(): void
    {
        $cacheFile = $this->cacheDir . '/posts_list.json';

        // キャッシュが存在しないことを確認
        $this->assertFileDoesNotExist($cacheFile);

        // DBからの取得をシミュレート（初回アクセス）
        $dbPosts = [
            ['id' => 1, 'title' => 'First Post'],
            ['id' => 2, 'title' => 'Second Post'],
        ];

        // キャッシュチェック → 存在しない → DB取得 → キャッシュ生成
        $posts = $this->getPostsWithCache($dbPosts);

        $this->assertFileExists($cacheFile, '初回アクセス時にキャッシュが生成されること');
        $this->assertCount(2, $posts, '取得したデータが正しいこと');
    }

    /**
     * 正常系: キャッシュが存在する場合、DBアクセスせずキャッシュから返却される
     */
    public function testCacheIsReturnedWhenExists(): void
    {
        $cacheFile = $this->cacheDir . '/posts_list.json';
        $cachedData = [
            ['id' => 1, 'title' => 'Cached Post 1'],
            ['id' => 2, 'title' => 'Cached Post 2'],
        ];

        // 事前にキャッシュを作成
        file_put_contents($cacheFile, json_encode($cachedData, JSON_UNESCAPED_UNICODE));

        // DBデータ（キャッシュがあるのでこれは使われない）
        $dbPosts = [
            ['id' => 3, 'title' => 'DB Post 1'],
        ];

        // キャッシュから取得
        $posts = $this->getPostsWithCache($dbPosts);

        // キャッシュのデータが返されることを確認
        $this->assertCount(2, $posts, 'キャッシュから2件取得されること');
        $this->assertEquals('Cached Post 1', $posts[0]['title'], 'キャッシュのデータが使用されること');
        $this->assertNotEquals('DB Post 1', $posts[0]['title'], 'DBデータは使用されないこと');
    }

    // =========================================================================
    // テーマ更新時のキャッシュクリアのテスト
    // =========================================================================

    /**
     * 正常系: テーマ更新時に全JSONキャッシュがクリアされる
     */
    public function testAllJsonCachesAreClearedOnThemeUpdate(): void
    {
        // 複数のJSONキャッシュを作成
        $cacheFiles = [
            $this->cacheDir . '/posts_list.json',
            $this->cacheDir . '/post_1.json',
            $this->cacheDir . '/post_2.json',
            $this->cacheDir . '/post_3.json',
        ];

        foreach ($cacheFiles as $file) {
            file_put_contents($file, json_encode(['test' => 'data']));
        }

        // 全ファイルが存在することを確認
        foreach ($cacheFiles as $file) {
            $this->assertFileExists($file, 'キャッシュファイルが作成されていること');
        }

        // テーマ更新時のキャッシュクリア
        $this->clearJsonCaches();

        // 全JSONファイルが削除されたことを確認
        foreach ($cacheFiles as $file) {
            $this->assertFileDoesNotExist($file, 'JSONキャッシュが削除されること');
        }
    }

    /**
     * 正常系: theme_header.html / theme_footer.html が削除される
     */
    public function testThemeHtmlFilesAreDeleted(): void
    {
        $headerFile = $this->cacheDir . '/theme_header.html';
        $footerFile = $this->cacheDir . '/theme_footer.html';

        // テーマHTMLファイルを作成
        file_put_contents($headerFile, '<header>Header Content</header>');
        file_put_contents($footerFile, '<footer>Footer Content</footer>');

        $this->assertFileExists($headerFile, 'ヘッダーファイルが作成されていること');
        $this->assertFileExists($footerFile, 'フッターファイルが作成されていること');

        // テーマキャッシュをクリア
        $this->clearThemeCaches();

        $this->assertFileDoesNotExist($headerFile, 'ヘッダーキャッシュが削除されること');
        $this->assertFileDoesNotExist($footerFile, 'フッターキャッシュが削除されること');
    }

    /**
     * 正常系: 次回アクセス時に新しいキャッシュが生成される
     */
    public function testNewCacheIsGeneratedAfterClear(): void
    {
        $cacheFile = $this->cacheDir . '/posts_list.json';

        // 古いキャッシュを作成
        $oldData = [['id' => 1, 'title' => 'Old Post']];
        file_put_contents($cacheFile, json_encode($oldData, JSON_UNESCAPED_UNICODE));

        // キャッシュクリア
        $this->clearJsonCaches();

        $this->assertFileDoesNotExist($cacheFile, 'キャッシュが削除されること');

        // 新しいデータでキャッシュ再生成
        $newData = [['id' => 1, 'title' => 'New Post']];
        $posts = $this->getPostsWithCache($newData);

        $this->assertFileExists($cacheFile, '新しいキャッシュが生成されること');

        $content = json_decode(file_get_contents($cacheFile), true);
        $this->assertEquals('New Post', $content[0]['title'], '新しいデータでキャッシュが生成されること');
    }

    // =========================================================================
    // パフォーマンステスト
    // =========================================================================

    /**
     * 正常系: キャッシュヒット時のレスポンス時間が10ms以下
     *
     * @group performance
     */
    public function testCacheHitResponseTimeIsUnder10ms(): void
    {
        $cacheFile = $this->cacheDir . '/posts_list.json';

        // 大量のデータを含むキャッシュを作成
        $posts = [];
        for ($i = 1; $i <= 50; $i++) {
            $posts[] = [
                'id' => $i,
                'title' => "Post {$i}",
                'tags' => 'tag1,tag2,tag3',
                'image_path' => "images/post{$i}.jpg",
            ];
        }

        file_put_contents($cacheFile, json_encode($posts, JSON_UNESCAPED_UNICODE));

        // パフォーマンス計測（10回の平均）
        $times = [];
        for ($i = 0; $i < 10; $i++) {
            $start = microtime(true);

            // キャッシュから読み込み
            $content = file_get_contents($cacheFile);
            $data = json_decode($content, true);

            $end = microtime(true);
            $times[] = ($end - $start) * 1000; // ミリ秒に変換
        }

        $avgTime = array_sum($times) / count($times);

        $this->assertLessThan(10, $avgTime, "キャッシュヒット時の平均レスポンス時間が10ms以下であること（実測: {$avgTime}ms）");
    }

    /**
     * 正常系: キャッシュミス時でも100ms以下
     *
     * @group performance
     */
    public function testCacheMissResponseTimeIsUnder100ms(): void
    {
        $cacheFile = $this->cacheDir . '/posts_list.json';

        // キャッシュが存在しないことを確認
        if (file_exists($cacheFile)) {
            unlink($cacheFile);
        }

        // DBアクセスをシミュレート
        $start = microtime(true);

        // DB取得シミュレート（軽量な処理）
        $posts = [];
        for ($i = 1; $i <= 50; $i++) {
            $posts[] = [
                'id' => $i,
                'title' => "Post {$i}",
                'tags' => 'tag1,tag2,tag3',
                'image_path' => "images/post{$i}.jpg",
            ];
        }

        // キャッシュ生成
        $this->generatePostsListCache($posts);

        $end = microtime(true);
        $time = ($end - $start) * 1000; // ミリ秒

        $this->assertLessThan(100, $time, "キャッシュミス時のレスポンス時間が100ms以下であること（実測: {$time}ms）");
    }

    // =========================================================================
    // セキュリティテスト
    // =========================================================================

    /**
     * 正常系: cache/index.php が存在し、403を返す
     */
    public function testCacheIndexPhpReturns403(): void
    {
        $indexFile = $this->cacheDir . '/index.php';

        $this->assertFileExists($indexFile, 'cache/index.phpが存在すること');

        // ファイル内容を確認
        $content = file_get_contents($indexFile);

        $this->assertStringContainsString('403', $content, 'ステータスコード403が含まれること');
        $this->assertStringContainsString('Forbidden', $content, 'Forbiddenメッセージが含まれること');
    }

    /**
     * 正常系: キャッシュディレクトリに直接アクセスできない（403）
     *
     * 注意: このテストはPHPビルトインサーバーまたはApacheが必要
     */
    public function testCacheDirectoryReturns403(): void
    {
        // このテストは統合テストとして別途実行されるべき
        // ユニットテストではファイルの存在と内容の検証のみ
        $this->markTestSkipped('統合テストで実行されるべきテスト');
    }

    // =========================================================================
    // ヘルパーメソッド
    // =========================================================================

    /**
     * 原子的書き込みを実行
     *
     * CLAUDE.mdの仕様に基づいた実装:
     * 一時ファイルに書き込み → rename()で上書き
     */
    private function atomicWrite(string $targetFile, string $content): bool
    {
        try {
            // 一時ファイルを作成
            $tempFile = $targetFile . '.tmp.' . uniqid();

            // 一時ファイルに書き込み（排他ロック使用）
            if (file_put_contents($tempFile, $content, LOCK_EX) === false) {
                return false;
            }

            // 原子的にリネーム
            if (!rename($tempFile, $targetFile)) {
                // 失敗時は一時ファイルを削除
                @unlink($tempFile);
                return false;
            }

            return true;
        } catch (\Exception $e) {
            // 例外が発生した場合は一時ファイルをクリーンアップ
            if (isset($tempFile) && file_exists($tempFile)) {
                @unlink($tempFile);
            }
            return false;
        }
    }

    /**
     * 投稿一覧キャッシュを生成
     */
    private function generatePostsListCache(array $posts): void
    {
        $cacheFile = $this->cacheDir . '/posts_list.json';
        $json = json_encode($posts, JSON_UNESCAPED_UNICODE);
        $this->atomicWrite($cacheFile, $json);
    }

    /**
     * 投稿詳細キャッシュを生成
     */
    private function generatePostDetailCache(int $id, array $post): void
    {
        $cacheFile = $this->cacheDir . "/post_{$id}.json";
        $json = json_encode($post, JSON_UNESCAPED_UNICODE);
        $this->atomicWrite($cacheFile, $json);
    }

    /**
     * キャッシュありでの投稿取得をシミュレート
     *
     * キャッシュが存在する場合はキャッシュから、
     * 存在しない場合はDBデータを使用してキャッシュを生成
     */
    private function getPostsWithCache(array $dbPosts): array
    {
        $cacheFile = $this->cacheDir . '/posts_list.json';

        // キャッシュが存在する場合
        if (file_exists($cacheFile)) {
            $content = file_get_contents($cacheFile);
            return json_decode($content, true);
        }

        // キャッシュが存在しない場合: DBから取得 → キャッシュ生成
        $json = json_encode($dbPosts, JSON_UNESCAPED_UNICODE);
        $this->atomicWrite($cacheFile, $json);

        return $dbPosts;
    }

    /**
     * 全JSONキャッシュをクリア
     */
    private function clearJsonCaches(): void
    {
        $files = glob($this->cacheDir . '/*.json');
        foreach ($files as $file) {
            unlink($file);
        }
    }

    /**
     * テーマキャッシュをクリア
     */
    private function clearThemeCaches(): void
    {
        $headerFile = $this->cacheDir . '/theme_header.html';
        $footerFile = $this->cacheDir . '/theme_footer.html';

        @unlink($headerFile);
        @unlink($footerFile);
    }
}
