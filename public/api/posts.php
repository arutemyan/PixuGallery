<?php

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Cache\CacheManager;
use App\Models\Post;
use App\Security\RateLimiter;
use App\Utils\Logger;
use App\Controllers\PublicControllerBase;

// CORS設定を読み込み
$config = \App\Config\ConfigManager::getInstance()->getConfig();
$corsConfig = $config['security']['cors'] ?? [];

if (!empty($corsConfig['enabled'])) {
    $allowedOrigins = $corsConfig['allowed_origins'] ?? ['*'];
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    
    // オリジンの検証
    if (in_array('*', $allowedOrigins, true)) {
        header('Access-Control-Allow-Origin: *');
    } elseif (in_array($origin, $allowedOrigins, true)) {
        header('Access-Control-Allow-Origin: ' . $origin);
        header('Vary: Origin');
    }
    
    $methods = implode(', ', $corsConfig['allowed_methods'] ?? ['GET', 'OPTIONS']);
    $headers = implode(', ', $corsConfig['allowed_headers'] ?? ['Content-Type', 'X-CSRF-Token']);
    
    header('Access-Control-Allow-Methods: ' . $methods);
    header('Access-Control-Allow-Headers: ' . $headers);
    
    if (!empty($corsConfig['allow_credentials'])) {
        header('Access-Control-Allow-Credentials: true');
    }
    
    if (!empty($corsConfig['max_age'])) {
        header('Access-Control-Max-Age: ' . $corsConfig['max_age']);
    }
}

if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// Define a lightweight controller class inline so the API is self-contained.
class PostsPublicController extends PublicControllerBase
{
    protected function onProcess(string $method): void
    {
        $rateLimiter = new RateLimiter(\App\Utils\PathHelper::getRateLimitDir(), 100, 60);
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
