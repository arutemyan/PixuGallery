<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../src/Security/SecurityUtil.php';

use App\Controllers\AdminControllerBase;
use App\Models\Post;
use App\Utils\ImageUploader;
use App\Cache\CacheManager;

class BulkUploadController extends AdminControllerBase
{
    private Post $postModel;

    public function __construct()
    {
        $this->postModel = new Post();
    }

    protected function onProcess(string $method): void
    {
        if ($method !== 'POST') {
            $this->sendError('POSTメソッドが必要です', 405);
        }

        $this->handleBulkUpload();
    }

    private function handleBulkUpload(): void
    {
        // アップロードされたファイルを確認
        if (empty($_FILES['images'])) {
            $this->sendError('画像ファイルが選択されていません');
        }

        $uploadedFiles = $_FILES['images'];

        // ImageUploaderを初期化
        $imageUploader = new ImageUploader(
            __DIR__ . '/../../uploads/images',
            __DIR__ . '/../../uploads/thumbs',
            20 * 1024 * 1024 // 20MB
        );

        $results = [];
        $successCount = 0;
        $errorCount = 0;

        // 複数ファイルの処理
        $fileCount = count($uploadedFiles['name']);

        for ($i = 0; $i < $fileCount; $i++) {
            $filename = $uploadedFiles['name'][$i];
            $tmpPath = $uploadedFiles['tmp_name'][$i];

            // ファイル配列を構築
            $file = [
                'name' => $uploadedFiles['name'][$i],
                'tmp_name' => $tmpPath,
                'error' => $uploadedFiles['error'][$i],
                'size' => $uploadedFiles['size'][$i]
            ];

            // ファイル検証
            $validation = $imageUploader->validateFile($file);
            if (!$validation['valid']) {
                $results[] = [
                    'filename' => $filename,
                    'success' => false,
                    'error' => $validation['error']
                ];
                $errorCount++;
                continue;
            }

            try {
                // ユニークなファイル名を生成
                $uniqueName = $imageUploader->generateUniqueFilename('bulk_');

                // 画像を処理して保存
                $uploadResult = $imageUploader->processAndSave(
                    $tmpPath,
                    $validation['mime_type'],
                    $uniqueName,
                    false // ぼかし版は作成しない
                );

                if (!$uploadResult['success']) {
                    throw new Exception($uploadResult['error']);
                }

                // DBに登録（非表示状態で）
                $postId = $this->postModel->createBulk(
                    $uploadResult['image_path'],
                    $uploadResult['thumb_path']
                );

                $results[] = [
                    'filename' => $filename,
                    'success' => true,
                    'post_id' => $postId
                ];
                $successCount++;

            } catch (Exception $e) {
                $results[] = [
                    'filename' => $filename,
                    'success' => false,
                    'error' => $e->getMessage()
                ];
                $errorCount++;
            }
        }

        // キャッシュを無効化
        if ($successCount > 0) {
            $cache = new CacheManager();
            $cache->invalidateAllPosts();
        }

        // レスポンス
        $this->sendSuccess([
            'total' => $fileCount,
            'success_count' => $successCount,
            'error_count' => $errorCount,
            'results' => $results
        ]);
    }
}

// コントローラーを実行
$controller = new BulkUploadController();
$controller->execute();
