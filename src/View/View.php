<?php

declare(strict_types=1);

namespace App\View;

use App\Config\ConfigManager;

/**
 * シンプルなテンプレートエンジン
 */
class View
{
    /**
     * 公開サイト用レイアウトでレンダリング
     *
     * @param string $contentView コンテンツテンプレートファイル名（拡張子なし）
     * @param array $data テンプレートに渡すデータ
     * @return void
     */
    public static function render(string $contentView, array $data = []): void
    {
        self::renderWithLayout('public', $contentView, $data);
    }

    /**
     * 管理画面用レイアウトでレンダリング
     *
     * @param string $contentView コンテンツテンプレートファイル名（拡張子なし）
     * @param array $data テンプレートに渡すデータ
     * @return void
     */
    public static function renderAdmin(string $contentView, array $data = []): void
    {
        self::renderWithLayout('admin', $contentView, $data);
    }

    /**
     * 指定されたレイアウトでレンダリング
     *
     * @param string $layout レイアウト名（'public' or 'admin'）
     * @param string $contentView コンテンツテンプレートファイル名
     * @param array $data テンプレートに渡すデータ
     * @return void
     */
    private static function renderWithLayout(string $layout, string $contentView, array $data = []): void
    {
        // データを変数として展開
        extract($data);

        // コンテンツ部分をバッファリング
        ob_start();
        $contentPath = __DIR__ . "/../../templates/pages/{$contentView}.php";
        if (!file_exists($contentPath)) {
            throw new \RuntimeException("View not found: {$contentView}");
        }
        require $contentPath;
        $content = ob_get_clean();

        // レイアウトに埋め込んで出力
        $layoutPath = __DIR__ . "/../../templates/layouts/{$layout}.php";
        if (!file_exists($layoutPath)) {
            throw new \RuntimeException("Layout not found: {$layout}");
        }
        require $layoutPath;
    }

    /**
     * Partialテンプレートをインクルード
     *
     * @param string $partial Partialファイル名（拡張子なし）
     * @param array $data Partialに渡すデータ
     * @return void
     */
    public static function partial(string $partial, array $data = []): void
    {
        extract($data);
        $partialPath = __DIR__ . "/../../templates/partials/{$partial}.php";
        if (!file_exists($partialPath)) {
            throw new \RuntimeException("Partial not found: {$partial}");
        }
        require $partialPath;
    }

    /**
     * ベースURLを取得
     *
     * @param string $path 追加するパス
     * @return string
     */
    public static function url(string $path = ''): string
    {
        $config = ConfigManager::getInstance()->getConfig();
        $baseUrl = $config['app']['base_url'] ?? '';
        $path = ltrim($path, '/');

        if (empty($baseUrl)) {
            return '/' . $path;
        }

        return rtrim($baseUrl, '/') . '/' . $path;
    }

    /**
     * アセットURLを取得
     *
     * @param string $path アセットパス
     * @return string
     */
    public static function asset(string $path): string
    {
        return self::url($path);
    }
}
