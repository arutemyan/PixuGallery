<?php
/**
 * タグ一覧API
 *
 * すべてのタグまたは人気のタグを取得するAPIエンドポイント
 *
 * リクエスト:
 *   GET /api/tags.php (すべてのタグ)
 *   GET /api/tags.php?popular=10 (人気タグトップ10)
 *   GET /api/tags.php?search=検索文字列 (タグ名で検索)
 *
 * レスポンス:
 *   {
 *     "success": true,
 *     "count": 15,
 *     "tags": [
 *       {
 *         "id": 1,
 *         "name": "風景",
 *         "post_count": 10
 *       },
 *       ...
 *     ]
 *   }
 */

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

// API 呼び出し時もメンテナンスなら 503 を返す
try {
    if (class_exists('App\\Utils\\Maintenance')) {
        \App\Utils\Maintenance::enforceForApi();
    }
} catch (\Throwable $e) {
    // ignore
}

use App\Models\Tag;
use App\Utils\Logger;
use App\Controllers\PublicControllerBase;

class TagsPublicController extends PublicControllerBase
{
    // Controller-level defaults
    protected bool $allowCors = true;
    protected bool $startSession = false;

    protected function onProcess(string $method): void
    {
        try {
            $tagModel = new Tag();

            // 人気タグを取得
            if (isset($_GET['popular'])) {
                $limit = (int)$_GET['popular'];

                // limitの範囲チェック
                if ($limit < 1 || $limit > 50) {
                    $this->sendError('Popular limit must be between 1 and 50', 400);
                }

                $tags = $tagModel->getPopular($limit);
                $this->sendSuccess(['count' => count($tags), 'tags' => $tags]);
            }

            // タグ名で検索
            if (isset($_GET['search'])) {
                $searchQuery = trim($_GET['search']);

                if (empty($searchQuery)) {
                    $this->sendError('Search query cannot be empty', 400);
                }

                // 禁止文字チェック: %, _, バックスラッシュは検索語に含められない
                if (preg_match('/[%_\\\\]/u', $searchQuery)) {
                    $this->sendError('検索語に使用できない文字が含まれています: %, _, \\', 400);
                }

                $tags = $tagModel->searchByName($searchQuery);
                $this->sendSuccess(['query' => $searchQuery, 'count' => count($tags), 'tags' => $tags]);
            }

            // すべてのタグを取得（デフォルト）
            $tags = $tagModel->getAll();
            $this->sendSuccess(['count' => count($tags), 'tags' => $tags]);

        } catch (Exception $e) {
            $this->handleError($e);
        }
    }
}

try {
    $controller = new TagsPublicController();
    $controller->execute();
} catch (Exception $e) {
    PublicControllerBase::handleException($e);
}
