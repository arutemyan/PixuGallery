<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../../vendor/autoload.php';
require_once __DIR__ . '/../../../../src/Security/SecurityUtil.php';

use App\Controllers\AdminControllerBase;
use App\Database\Connection;
use App\Utils\Logger;
use App\Services\Session;

class IllustDataController extends AdminControllerBase
{
    protected function onProcess(string $method): void
    {
        if ($method !== 'GET') {
            $this->sendError('Method not allowed', 405);
        }

        $id = isset($_GET['id']) ? (int)$_GET['id'] : null;
        if (!$id) {
            $this->sendError('Missing id', 400);
        }

        $db = Connection::getInstance();
        $stmt = $db->prepare('SELECT * FROM paint WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            $this->sendError('Not found', 404);
        }

        $dataPath = $row['data_path'] ?? null;
        if (!$dataPath) {
            $this->sendSuccess(['data' => null]);
        }

        // data_path is relative to public (e.g., /uploads/paintfiles/data/...)
        $publicRoot = __DIR__ . '/../../..';
        $abs = $publicRoot . $dataPath;

        if (!file_exists($abs)) {
            Logger::getInstance()->error("Paint data file not found: $abs (from data_path: $dataPath)");
            $this->sendError('Data file not found', 404);
        }

        $content = @file_get_contents($abs);
        if ($content === false) {
            $this->sendError('Failed to read file', 500);
        }

        $this->sendSuccess(['data' => json_decode($content, true)]);
    }
}

// コントローラーを実行
$controller = new IllustDataController();
$controller->execute();
