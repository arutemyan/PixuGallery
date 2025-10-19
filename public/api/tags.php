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

use App\Models\Tag;

header('Content-Type: application/json; charset=utf-8');

try {
    $tagModel = new Tag();

    // 人気タグを取得
    if (isset($_GET['popular'])) {
        $limit = (int)$_GET['popular'];

        // limitの範囲チェック
        if ($limit < 1 || $limit > 50) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => 'Popular limit must be between 1 and 50'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $tags = $tagModel->getPopular($limit);

        echo json_encode([
            'success' => true,
            'count' => count($tags),
            'tags' => $tags
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // タグ名で検索
    if (isset($_GET['search'])) {
        $searchQuery = trim($_GET['search']);

        if (empty($searchQuery)) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => 'Search query cannot be empty'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $tags = $tagModel->searchByName($searchQuery);

        echo json_encode([
            'success' => true,
            'query' => $searchQuery,
            'count' => count($tags),
            'tags' => $tags
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // すべてのタグを取得（デフォルト）
    $tags = $tagModel->getAll();

    echo json_encode([
        'success' => true,
        'count' => count($tags),
        'tags' => $tags
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Internal server error'
    ], JSON_UNESCAPED_UNICODE);
    error_log('Tags API Error: ' . $e->getMessage());
    error_log($e->getTraceAsString());
}
