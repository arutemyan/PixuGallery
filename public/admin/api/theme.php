<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../src/Security/SecurityUtil.php';

use App\Controllers\AdminControllerBase;
use App\Models\Theme;

class ThemeController extends AdminControllerBase
{
    private Theme $themeModel;

    public function __construct()
    {
        $this->themeModel = new Theme();
    }

    protected function onProcess(string $method): void
    {
        switch ($method) {
            case 'GET':
                $this->handleGet();
                break;
            case 'PUT':
            case 'POST':
                $this->handleUpdate();
                break;
            default:
                $this->sendError('PUTまたはPOSTメソッドのみ許可されています', 405);
        }
    }

    private function handleGet(): void
    {
        $theme = $this->themeModel->getCurrent();
        $this->sendSuccess(['theme' => $theme]);
    }

    private function handleUpdate(): void
    {
        // パラメータ取得と入力長検証
        $data = [];

        // サイト情報
        if (isset($_POST['site_title'])) {
            if (mb_strlen($_POST['site_title']) > 100) {
                $this->sendError('サイトタイトルは100文字以内で入力してください');
            }
            $data['site_title'] = $_POST['site_title'];
        }
        if (isset($_POST['site_subtitle'])) {
            if (mb_strlen($_POST['site_subtitle']) > 200) {
                $this->sendError('サイトサブタイトルは200文字以内で入力してください');
            }
            $data['site_subtitle'] = $_POST['site_subtitle'];
        }
        if (isset($_POST['site_description'])) {
            if (mb_strlen($_POST['site_description']) > 500) {
                $this->sendError('サイト説明は500文字以内で入力してください');
            }
            $data['site_description'] = $_POST['site_description'];
        }

        // カラーテーマ
        $colorFields = [
            'primary_color', 'secondary_color', 'accent_color', 'background_color',
            'text_color', 'heading_color', 'footer_bg_color', 'footer_text_color',
            'card_border_color', 'card_bg_color', 'card_shadow_opacity',
            'link_color', 'link_hover_color', 'tag_bg_color', 'tag_text_color',
            'filter_active_bg_color', 'filter_active_text_color'
        ];

        foreach ($colorFields as $field) {
            if (isset($_POST[$field])) {
                $data[$field] = $_POST[$field];
            }
        }

        // ナビゲーション設定（一覧に戻るボタン）
        if (isset($_POST['back_button_text'])) {
            if (mb_strlen($_POST['back_button_text']) > 20) {
                $this->sendError('ボタンテキストは20文字以内で入力してください');
            }
            $data['back_button_text'] = $_POST['back_button_text'];
        }
        if (isset($_POST['back_button_bg_color'])) {
            $data['back_button_bg_color'] = $_POST['back_button_bg_color'];
        }
        if (isset($_POST['back_button_text_color'])) {
            $data['back_button_text_color'] = $_POST['back_button_text_color'];
        }

        // 詳細ボタン設定
        if (isset($_POST['detail_button_text'])) {
            if (mb_strlen($_POST['detail_button_text']) > 20) {
                $this->sendError('ボタンテキストは20文字以内で入力してください');
            }
            $data['detail_button_text'] = $_POST['detail_button_text'];
        }
        if (isset($_POST['detail_button_bg_color'])) {
            $data['detail_button_bg_color'] = $_POST['detail_button_bg_color'];
        }
        if (isset($_POST['detail_button_text_color'])) {
            $data['detail_button_text_color'] = $_POST['detail_button_text_color'];
        }

        // カスタムHTML（XSS対策: HTMLタグを許可しない）
        if (isset($_POST['header_html'])) {
            if (mb_strlen($_POST['header_html']) > 5000) {
                $this->sendError('ヘッダーHTMLは5000文字以内で入力してください');
            }
            // HTMLタグを全て削除（XSS対策）
            $data['header_html'] = strip_tags($_POST['header_html']);
        }
        if (isset($_POST['footer_html'])) {
            if (mb_strlen($_POST['footer_html']) > 5000) {
                $this->sendError('フッターHTMLは5000文字以内で入力してください');
            }
            // HTMLタグを全て削除（XSS対策）
            $data['footer_html'] = strip_tags($_POST['footer_html']);
        }

        // データベースを更新
        $this->themeModel->update($data);

        // 成功レスポンス
        $this->sendSuccess(['message' => 'テーマが更新されました']);
    }
}

// コントローラーを実行
$controller = new ThemeController();
$controller->execute();
