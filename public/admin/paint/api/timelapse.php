<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../../vendor/autoload.php';
require_once __DIR__ . '/../../../../src/Security/SecurityUtil.php';

use App\Controllers\AdminControllerBase;
use App\Services\TimelapseService;
use App\Services\Session;

class TimelapseController extends AdminControllerBase
{
    protected function onProcess(string $method): void
    {
        if ($method !== 'GET') {
            $this->sendError('Method not allowed', 405);
        }

        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        $publicRoot = realpath(__DIR__ . '/../../..');  // public ディレクトリ

        $result = TimelapseService::getTimelapseData($id, $publicRoot);

        if (!$result['success']) {
            $statusCode = 400;
            if (strpos($result['error'], 'not found') !== false) {
                $statusCode = 404;
            } else if (strpos($result['error'], 'Server error') !== false) {
                $statusCode = 500;
            }
            $this->sendError($result['error'], $statusCode);
        }

        // Send the result directly (already contains 'success' => true)
        http_response_code(200);
        header('Content-Type: application/json');
        echo json_encode($result, JSON_UNESCAPED_UNICODE);
        exit;
    }
}

// コントローラーを実行
$controller = new TimelapseController();
$controller->execute();
