<?php

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Cache\CacheManager;
use App\Models\Post;
use App\Security\RateLimiter;
use App\Utils\Logger;
use App\Controllers\PublicControllerBase;

// CORS: allow simple cross-origin GET usage and handle preflight
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token');
if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// Define a lightweight controller class inline so the API is self-contained.
class PostsPublicController extends PublicControllerBase
{
    protected function onProcess(string $method): void
    {
        $rateLimiter = new RateLimiter(__DIR__ . '/../../data/rate-limits', 100, 60);
        $clientIp = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

        if (!$rateLimiter->check($clientIp, 'api_posts')) {
            $retryAfter = $rateLimiter->getRetryAfter($clientIp, 'api_posts');
            if ($retryAfter) {
                header('Retry-After: ' . ($retryAfter - time()));
            }
            $this->sendError('Too many requests', 429);
        }

        $rateLimiter->record($clientIp, 'api_posts');

        try {
            $postModel = new Post();

            // Single post
            if (isset($_GET['id']) && is_numeric($_GET['id'])) {
                $postId = (int)$_GET['id'];
                $post = $postModel->getById($postId);
                if ($post === null) {
                    $this->sendError('投稿が見つかりません', 404);
                }
                $this->sendSuccess(['post' => $post]);
            }

            // List posts
            $nsfwFilter = $_GET['nsfw_filter'] ?? 'all';
            $tagId = isset($_GET['tagId']) && is_numeric($_GET['tagId']) ? (int)$_GET['tagId'] : null;
            $limit = isset($_GET['limit']) ? min((int)$_GET['limit'], 30) : 18;
            $offset = isset($_GET['offset']) ? max((int)$_GET['offset'], 0) : 0;

            $cache = new CacheManager();
            $useCache = ($nsfwFilter === 'all' && $tagId === null && $offset === 0 && $limit === 18);

            if ($useCache && $cache->has('posts_list')) {
                $cachedData = $cache->readRaw('posts_list');
                if ($cachedData !== null) {
                    echo $cachedData;
                    exit;
                }
            }

            $posts = $postModel->getAllUnified($limit, $nsfwFilter, $tagId, $offset);
            foreach ($posts as &$post) {
                $post['post_type'] = $post['post_type'] == 1 ? 'group' : 'single';
            }

            $response = [
                'count' => count($posts),
                'posts' => $posts,
            ];

            if ($useCache) {
                $cache->set('posts_list', $response);
            }

            $this->sendSuccess($response);

        } catch (Exception $e) {
            // Let the controller base handle logging and response
            $this->handleError($e);
        }
    }
}

try {
    $controller = new PostsPublicController();
    $controller->execute();
} catch (Exception $e) {
    // Delegate to base handler (it will log and produce a 500 JSON)
    PublicControllerBase::handleException($e);
}
