<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../src/Security/SecurityUtil.php';

use App\Controllers\AdminControllerBase;
use App\Models\Setting;

class OgpImageController extends AdminControllerBase
{
    private Setting $settingModel;

    public function __construct()
    {
        $this->settingModel = new Setting();
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
        // 現在のOGP画像パスを取得
        $currentImagePath = $this->settingModel->get('ogp_image');

        // 画像ファイルを削除
        if ($currentImagePath) {
            $fullPath = __DIR__ . '/../../' . $currentImagePath;
            if (file_exists($fullPath)) {
                unlink($fullPath);
            }
        }

        // データベースを更新（画像パスを空に）
        $this->settingModel->set('ogp_image', '');

        $this->sendSuccess(['message' => 'OGP画像が削除されました']);
    }

    private function handleUpload(): void
    {
        // 画像ファイルチェック
        if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
            $this->sendError('画像ファイルをアップロードしてください');
        }

        $file = $_FILES['image'];
        $allowedTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];

        // MIME typeチェック
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mimeType, $allowedTypes)) {
            $this->sendError('画像ファイル（JPEG, PNG, WebP, GIF）のみアップロード可能です');
        }

        // ファイルサイズチェック (5MB)
        if ($file['size'] > 5 * 1024 * 1024) {
            $this->sendError('ファイルサイズは5MB以下にしてください');
        }

        // アップロードディレクトリ
        $uploadDir = __DIR__ . '/../../uploads/ogp';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        // ファイル名生成（既存のOGP画像を上書き）
        $extension = match($mimeType) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
            default => 'jpg'
        };
        $filename = 'ogp-image.' . $extension;
        $filepath = $uploadDir . '/' . $filename;

        // 既存のOGP画像を削除
        foreach (glob($uploadDir . '/ogp-image.*') as $oldFile) {
            @unlink($oldFile);
        }

        // ファイルを移動
        if (!move_uploaded_file($file['tmp_name'], $filepath)) {
            $this->sendError('ファイルの保存に失敗しました', 500);
        }

        // パーミッション設定
        chmod($filepath, 0644);

        // 相対パスを生成
        $relativePath = 'uploads/ogp/' . $filename;

        // データベースに保存
        $this->settingModel->set('ogp_image', $relativePath);

        $this->sendSuccess([
            'message' => 'OGP画像がアップロードされました',
            'image_path' => $relativePath
        ]);
    }
}

// コントローラーを実行
$controller = new OgpImageController();
$controller->execute();
