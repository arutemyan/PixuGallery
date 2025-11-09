<?php
/**
 * イラスト一覧取得API
 * public/paint/ 専用
 */

require_once(__DIR__ . '/../../../vendor/autoload.php');
$config = \App\Config\ConfigManager::getInstance()->getConfig();

use App\Controllers\PublicControllerBase;

class IllustsPublicController extends PublicControllerBase
{
    protected bool $startSession = false;
    protected bool $allowCors = false;

    protected function onProcess(string $method): void
    {
        try {
            $db = \App\Database\Connection::getInstance();

            // パラメータ取得
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
            $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
            $tag = isset($_GET['tag']) ? trim($_GET['tag']) : null;
            $search = isset($_GET['search']) ? trim($_GET['search']) : null;

            $limit = max(1, min($limit, 100)); // 1-100の範囲
            $offset = max(0, $offset);

            // ベースクエリ
            $sql = "SELECT 
                        i.id,
                        i.title,
                        '' as detail,
                        i.image_path,
                        i.thumbnail_path as thumb_path,
                        i.data_path,
                        i.timelapse_path,
                        i.canvas_width as width,
                        i.canvas_height as height,
                        i.created_at,
                        i.updated_at,
                        '' as tags
                    FROM paint i";

            $where = [];
            $params = [];

            // 検索フィルター
            if ($search) {
                $where[] = "i.title LIKE :search";
                $params[':search'] = '%' . $search . '%';
            }

            if (!empty($where)) {
                $sql .= " WHERE " . implode(' AND ', $where);
            }

            $sql .= " ORDER BY i.created_at DESC LIMIT :limit OFFSET :offset";

            $stmt = $db->prepare($sql);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

            $stmt->execute();
            $paints = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // 総数を取得
            $countSql = "SELECT COUNT(i.id) as total FROM paint i";
            if (!empty($where)) {
                $countSql .= " WHERE " . implode(' AND ', $where);
            }

            $countStmt = $db->prepare($countSql);
            foreach ($params as $key => $value) {
                if ($key !== ':limit' && $key !== ':offset') {
                    $countStmt->bindValue($key, $value);
                }
            }
            $countStmt->execute();
            $total = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];

            $this->sendSuccess([
                'paint' => $paints,
                'total' => (int)$total,
                'limit' => $limit,
                'offset' => $offset
            ]);

        } catch (Exception $e) {
            $this->handleError($e);
        }
    }
}

try {
    $controller = new IllustsPublicController();
    $controller->execute();
} catch (Exception $e) {
    PublicControllerBase::handleException($e);
}
