<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../../vendor/autoload.php';
require_once __DIR__ . '/../../../../src/Security/SecurityUtil.php';

use App\Controllers\AdminControllerBase;
use App\Database\Connection;
use App\Models\Paint;
use App\Services\Session;

class PaintLoadController extends AdminControllerBase
{
    private Paint $paintModel;

    public function __construct()
    {
        $db = Connection::getInstance();
        $this->paintModel = new Paint($db);
    }

    protected function onProcess(string $method): void
    {
        if ($method !== 'GET') {
            $this->sendError('Method not allowed', 405);
        }

        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        if ($id <= 0) {
            $this->sendError('Invalid id', 400);
        }

        $row = $this->paintModel->findById($id);
        if (!$row) {
            $this->sendError('Not found', 404);
        }

        // Load illust_data from file if data_path exists
        if (!empty($row['data_path'])) {
            // Construct absolute path (data_path is like /uploads/paintfiles/data/...)
            $dataPath = __DIR__ . '/../../..' . $row['data_path'];
            if (file_exists($dataPath)) {
                $illustData = @file_get_contents($dataPath);
                if ($illustData !== false) {
                    $row['illust_data'] = $illustData;
                }
            }
        }

        $this->sendSuccess(['data' => $row]);
    }
}

// コントローラーを実行
$controller = new PaintLoadController();
$controller->execute();
