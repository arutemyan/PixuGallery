<?php

declare(strict_types=1);

/**
 * 公開API ルーター
 *
 * すべての公開APIエンドポイントをここで管理
 */

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Http\Router;
use App\Models\Post;
use App\Models\Tag;

$router = new Router();

// CORS設定（必要に応じて）
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');

/**
 * GET /api/posts
 * 投稿一覧を取得（表示状態のみ）
 */
$router->get('/api/posts', function () {
    try {
        $postModel = new Post();

        // フィルタパラメータ
        $nsfwFilter = $_GET['nsfw_filter'] ?? 'all';
        $tagId = isset($_GET['tagId']) && is_numeric($_GET['tagId']) ? (int)$_GET['tagId'] : null;
        $limit = isset($_GET['limit']) ? min((int)$_GET['limit'], 30) : 18;
        $offset = isset($_GET['offset']) ? max((int)$_GET['offset'], 0) : 0;

        // 投稿を取得（is_visible=1のみ）
        $posts = $postModel->getAll($limit, $nsfwFilter, $tagId, $offset);

        Router::json([
            'success' => true,
            'count' => count($posts),
            'posts' => $posts
        ]);
    } catch (Exception $e) {
        error_log('API Error (GET /api/posts): ' . $e->getMessage());
        Router::error('サーバーエラーが発生しました', 500);
    }
});

/**
 * GET /api/posts/:id
 * 単一投稿を取得（表示状態のみ）
 */
$router->get('/api/posts/:id', function (string $id) {
    try {
        $postId = (int)$id;
        $postModel = new Post();
        $post = $postModel->getById($postId);

        if ($post === null) {
            Router::error('投稿が見つかりません', 404);
            return;
        }

        Router::json([
            'success' => true,
            'post' => $post
        ]);
    } catch (Exception $e) {
        error_log('API Error (GET /api/posts/:id): ' . $e->getMessage());
        Router::error('サーバーエラーが発生しました', 500);
    }
});

/**
 * GET /api/tags
 * タグ一覧を取得
 */
$router->get('/api/tags', function () {
    try {
        $tagModel = new Tag();
        $popular = isset($_GET['popular']) ? (int)$_GET['popular'] : null;

        if ($popular !== null) {
            $tags = $tagModel->getPopular($popular);
        } else {
            $tags = $tagModel->getAll();
        }

        Router::json([
            'success' => true,
            'tags' => $tags
        ]);
    } catch (Exception $e) {
        error_log('API Error (GET /api/tags): ' . $e->getMessage());
        Router::error('サーバーエラーが発生しました', 500);
    }
});

/**
 * POST /api/increment_view
 * 閲覧回数をインクリメント
 */
$router->post('/api/increment_view', function () {
    try {
        $postId = isset($_POST['id']) ? (int)$_POST['id'] : 0;

        if ($postId <= 0) {
            Router::error('投稿IDが不正です', 400);
            return;
        }

        $postModel = new Post();
        $result = $postModel->incrementViewCount($postId);

        Router::json([
            'success' => $result
        ]);
    } catch (Exception $e) {
        error_log('API Error (POST /api/increment_view): ' . $e->getMessage());
        Router::error('サーバーエラーが発生しました', 500);
    }
});

// ルーティングを実行
$router->dispatch();
