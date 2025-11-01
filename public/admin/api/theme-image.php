<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../src/Security/SecurityUtil.php';

use App\Models\Theme;
use App\Security\CsrfProtection;

// セッション開始
initSecureSession();

// 認証チェック
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => '認証が必要です'], JSON_UNESCAPED_UNICODE);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

// HTTPメソッドを確認
$method = $_SERVER['REQUEST_METHOD'];
if ($method === 'POST' && isset($_POST['_method']) && strtoupper($_POST['_method']) === 'DELETE') {
    $method = 'DELETE';
}

// POSTまたはDELETEのみ許可
if ($method !== 'POST' && $method !== 'DELETE') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'POSTまたはDELETEメソッドのみ許可されています'], JSON_UNESCAPED_UNICODE);
    exit;
}

// CSRFトークン検証
if (!CsrfProtection::validatePost() && !CsrfProtection::validateHeader()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'CSRFトークンが無効です'], JSON_UNESCAPED_UNICODE);
    logSecurityEvent('CSRF token validation failed on theme image operation', ['ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown']);
    exit;
}

// DELETEリクエスト: 画像削除
if ($method === 'DELETE') {
    try {
        // 画像タイプを確認（logo または header）
        $imageType = $_POST['image_type'] ?? '';

        if (!in_array($imageType, ['logo', 'header'])) {
            throw new Exception('無効な画像タイプです');
        }

        // 現在の画像パスを取得
        $themeModel = new Theme();
        $currentTheme = $themeModel->getCurrent();
        $fieldName = $imageType === 'logo' ? 'logo_image' : 'header_image';
        $currentImagePath = $currentTheme[$fieldName] ?? null;

        // 画像ファイルを削除
        if ($currentImagePath) {
            $fullPath = __DIR__ . '/../../' . $currentImagePath;
            if (file_exists($fullPath)) {
                unlink($fullPath);
            }
        }

        // データベースを更新（画像パスをNULLに）
        $themeModel->updateImage($fieldName, null);

        // 成功レスポンス
        echo json_encode([
            'success' => true,
            'message' => '画像が削除されました'
        ], JSON_UNESCAPED_UNICODE);

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ], JSON_UNESCAPED_UNICODE);

        error_log('Theme Image Delete Error: ' . $e->getMessage());
    }
    exit;
}

// POSTリクエスト: 画像アップロード
try {
    // 画像タイプを確認（logo または header）
    $imageType = $_POST['image_type'] ?? '';

    if (!in_array($imageType, ['logo', 'header'])) {
        throw new Exception('無効な画像タイプです');
    }

    // ファイルアップロードチェック
    if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('画像ファイルがアップロードされていません');
    }

    $file = $_FILES['image'];

    // ファイルサイズチェック（最大10MB）
    if ($file['size'] > 10 * 1024 * 1024) {
        throw new Exception('ファイルサイズが大きすぎます（最大10MB）');
    }

    // MIMEタイプチェック
    $allowedTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($mimeType, $allowedTypes)) {
        throw new Exception('画像ファイルのみアップロード可能です');
    }

    // アップロードディレクトリ
    $uploadDir = __DIR__ . '/../../uploads/theme/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    // ファイル名を生成
    $extension = match($mimeType) {
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
        default => 'jpg'
    };

    $filename = $imageType . '_' . time() . '.' . $extension;
    $filepath = $uploadDir . $filename;

    // WebPに変換して保存
    $image = match($mimeType) {
        'image/jpeg' => imagecreatefromjpeg($file['tmp_name']),
        'image/png' => imagecreatefrompng($file['tmp_name']),
        'image/webp' => imagecreatefromwebp($file['tmp_name']),
        'image/gif' => imagecreatefromgif($file['tmp_name']),
        default => throw new Exception('サポートされていない画像形式です')
    };

    if ($image === false) {
        throw new Exception('画像の読み込みに失敗しました');
    }

    // WebPとして保存
    $webpFilename = $imageType . '_' . time() . '.webp';
    $webpFilepath = $uploadDir . $webpFilename;

    if (!imagewebp($image, $webpFilepath, 90)) {
        imagedestroy($image);
        throw new Exception('画像の保存に失敗しました');
    }

    imagedestroy($image);

    // データベースを更新
    $themeModel = new Theme();
    $fieldName = $imageType === 'logo' ? 'logo_image' : 'header_image';
    $relativePath = 'uploads/theme/' . $webpFilename;

    $themeModel->updateImage($fieldName, $relativePath);

    // 成功レスポンス
    echo json_encode([
        'success' => true,
        'message' => '画像がアップロードされました',
        'image_path' => $relativePath
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);

    error_log('Theme Image Upload Error: ' . $e->getMessage());
}
