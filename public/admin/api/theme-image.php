<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../src/Security/SecurityUtil.php';

use App\Controllers\AdminControllerBase;
use App\Models\Theme;

class ThemeImageController extends AdminControllerBase
{
    private Theme $themeModel;

    public function __construct()
    {
        $this->themeModel = new Theme();
    }

    protected function onProcess(string $method): void
    {
        switch ($method) {
            case 'POST':
                $this->handleUpload();
                break;
            case 'DELETE':
                $this->handleDelete();
                break;
            default:
                $this->sendError('POSTまたはDELETEメソッドのみ許可されています', 405);
        }
    }

    private function handleDelete(): void
    {
        // 画像タイプを確認（logo または header）
        $imageType = $_POST['image_type'] ?? '';

        if (!in_array($imageType, ['logo', 'header'])) {
            $this->sendError('無効な画像タイプです');
        }

        // 現在の画像パスを取得
        $currentTheme = $this->themeModel->getCurrent();
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
        $this->themeModel->updateImage($fieldName, null);

        $this->sendSuccess(['message' => '画像が削除されました']);
    }

    private function handleUpload(): void
    {
        // 画像タイプを確認（logo または header）
        $imageType = $_POST['image_type'] ?? '';

        if (!in_array($imageType, ['logo', 'header'])) {
            $this->sendError('無効な画像タイプです');
        }

        // ファイルアップロードチェック
        if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
            $this->sendError('画像ファイルがアップロードされていません');
        }

        $file = $_FILES['image'];

        // ファイルサイズチェック（最大10MB）
        if ($file['size'] > 10 * 1024 * 1024) {
            $this->sendError('ファイルサイズが大きすぎます（最大10MB）');
        }

        // MIMEタイプチェック
        $allowedTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mimeType, $allowedTypes)) {
            $this->sendError('画像ファイルのみアップロード可能です');
        }

        // アップロードディレクトリ
        $uploadDir = \App\Utils\PathHelper::getUploadsDir() . '/theme/';
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
            default => $this->sendError('サポートされていない画像形式です')
        };

        if ($image === false) {
            $this->sendError('画像の読み込みに失敗しました');
        }

        // WebPとして保存
        $webpFilename = $imageType . '_' . time() . '.webp';
        $webpFilepath = $uploadDir . $webpFilename;

        if (!imagewebp($image, $webpFilepath, 90)) {
            imagedestroy($image);
            $this->sendError('画像の保存に失敗しました', 500);
        }

        imagedestroy($image);

        // データベースを更新
        $fieldName = $imageType === 'logo' ? 'logo_image' : 'header_image';
        $relativePath = 'uploads/theme/' . $webpFilename;

        $this->themeModel->updateImage($fieldName, $relativePath);

        $this->sendSuccess([
            'message' => '画像がアップロードされました',
            'image_path' => $relativePath
        ]);
    }
}

// コントローラーを実行
$controller = new ThemeImageController();
$controller->execute();
