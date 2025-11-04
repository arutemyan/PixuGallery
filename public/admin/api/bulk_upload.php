<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../auth_check.php';
require_once __DIR__ . '/../../../src/Security/SecurityUtil.php';
require_once __DIR__ . '/../../../src/Utils/Logger.php';

use App\Models\Post;
use App\Utils\ImageUploader;
use App\Security\CsrfProtection;
use App\Cache\CacheManager;
use App\Utils\Logger;

initSecureSession();

// 認証チェック
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode([
            'success' => false,
            'error' => 'POSTメソッドが必要です'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // CSRFトークン検証
    if (!CsrfProtection::validatePost()) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'error' => 'CSRFトークンが無効です'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // アップロードされたファイルを確認
    if (empty($_FILES['images'])) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => '画像ファイルが選択されていません'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $uploadedFiles = $_FILES['images'];
    $postModel = new Post();

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
            $postId = $postModel->createBulk(
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
    echo json_encode([
        'success' => true,
        'total' => $fileCount,
        'success_count' => $successCount,
        'error_count' => $errorCount,
        'results' => $results
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'サーバーエラーが発生しました'
    ], JSON_UNESCAPED_UNICODE);

    Logger::getInstance()->error('Bulk Upload Error: ' . $e->getMessage());
}
