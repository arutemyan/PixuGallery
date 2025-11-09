<?php

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Models\Post;
use App\Security\RateLimiter;
use App\Utils\Logger;
use App\Controllers\PublicControllerBase;

class IncrementViewPublicController extends PublicControllerBase
{
    // Configure controller behavior via instance properties
    protected bool $allowCors = true;
    protected bool $startSession = false;

    protected function onProcess(string $method): void
    {
        $rateLimiter = new RateLimiter(__DIR__ . '/../../data/rate-limits', 100, 60);
        $clientIp = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

        if (!$rateLimiter->check($clientIp, 'api_increment_view')) {
            $retryAfter = $rateLimiter->getRetryAfter($clientIp, 'api_increment_view');
            if ($retryAfter) {
                header('Retry-After: ' . ($retryAfter - time()));
            }
            $this->sendError('Too many requests', 429);
        }

        $rateLimiter->record($clientIp, 'api_increment_view');

        try {
            $postId = $_POST['id'] ?? $_GET['id'] ?? null;
            $viewType = $_POST['viewtype'] ?? $_GET['viewtype'] ?? 0;

            if (empty($postId) || !is_numeric($postId)) {
                $this->sendError('Invalid post ID', 400);
            }

            if (!is_numeric($viewType)) {
                $this->sendError('Invalid view type', 400);
            }

            $model = new Post();
            $success = $model->incrementViewCount((int)$postId);

            if ($success) {
                $this->sendSuccess();
            } else {
                $this->sendError('Post not found', 404);
            }

        } catch (Exception $e) {
            // Let base handler log and respond
            $this->handleError($e);
        }
    }
}

try {
    $controller = new IncrementViewPublicController();
    $controller->execute();
} catch (Exception $e) {
    PublicControllerBase::handleException($e);
}
