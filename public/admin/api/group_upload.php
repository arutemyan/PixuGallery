<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../auth_check.php';
require_once __DIR__ . '/../../../src/Security/SecurityUtil.php';

use App\Models\GroupPost;
use App\Utils\ImageUploader;
use App\Security\CsrfProtection;
use App\Cache\CacheManager;

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

    // タイトルの確認
    $title = isset($_POST['title']) ? trim($_POST['title']) : '';
    if (empty($title)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'タイトルは必須です'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $uploadedFiles = $_FILES['images'];
    $groupPostModel = new GroupPost();

    // ImageUploaderを初期化
    $imageUploader = new ImageUploader(
        __DIR__ . '/../../uploads/images',
        __DIR__ . '/../../uploads/thumbs',
        20 * 1024 * 1024 // 20MB
    );

    // メタデータ取得
    $tags = $_POST['tags'] ?? '';
    $detail = $_POST['detail'] ?? '';
    $isSensitive = isset($_POST['is_sensitive']) ? (int)$_POST['is_sensitive'] : 0;
    $isVisible = isset($_POST['is_visible']) ? (int)$_POST['is_visible'] : 1;

    $imagePaths = [];
    $results = [];
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
            $uniqueName = $imageUploader->generateUniqueFilename('group_');

            // 画像を処理して保存
            $uploadResult = $imageUploader->processAndSave(
                $tmpPath,
                $validation['mime_type'],
                $uniqueName,
                $isSensitive == 1 // NSFWフィルター版を作成
            );

            if (!$uploadResult['success']) {
                throw new Exception($uploadResult['error']);
            }

            // 画像パスを保存
            $imagePaths[] = [
                'image' => $uploadResult['image_path'],
                'thumb' => $uploadResult['thumb_path']
            ];

            $results[] = [
                'filename' => $filename,
                'success' => true
            ];

        } catch (Exception $e) {
            $results[] = [
                'filename' => $filename,
                'success' => false,
                'error' => $e->getMessage()
            ];
            $errorCount++;
        }
    }

    // 少なくとも1枚の画像が成功している必要がある
    if (empty($imagePaths)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => '有効な画像がアップロードされませんでした',
            'results' => $results
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // グループ投稿を作成
    $groupPostId = $groupPostModel->create(
        $title,
        $imagePaths,
        $tags,
        $detail,
        $isSensitive,
        $isVisible
    );

    // キャッシュを無効化
    $cache = new CacheManager();
    $cache->invalidateAllPosts();

    // レスポンス
    echo json_encode([
        'success' => true,
        'group_post_id' => $groupPostId,
        'total' => $fileCount,
        'success_count' => count($imagePaths),
        'error_count' => $errorCount,
        'results' => $results,
        'message' => "グループ投稿「{$title}」を作成しました（" . count($imagePaths) . "枚の画像）"
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'サーバーエラーが発生しました'
    ], JSON_UNESCAPED_UNICODE);

    error_log('Group Upload Error: ' . $e->getMessage());
}
