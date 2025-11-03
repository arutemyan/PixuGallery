<?php
/**
 * Color Palette API
 * カラーパレットの取得・更新
 */

declare(strict_types=1);

require_once __DIR__ . '/../../../../vendor/autoload.php';
require_once __DIR__ . '/../../../../config/config.php';
require_once __DIR__ . '/../../../../src/Security/SecurityUtil.php';

use App\Security\CsrfProtection;

header('Content-Type: application/json');

initSecureSession();

// Admin check
$isAdmin = false;
if (!empty($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    $isAdmin = true;
} elseif (!empty($_SESSION['admin']) && is_array($_SESSION['admin'])) {
    $isAdmin = true;
}

if (!$isAdmin) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$db = App\Database\Connection::getInstance();

// GET: カラーパレットを取得
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $stmt = $db->prepare("
            SELECT slot_index, color 
            FROM color_palettes 
            WHERE user_id IS NULL 
            ORDER BY slot_index ASC
        ");
        $stmt->execute();
        $palette = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Convert to simple array
        $colors = array_fill(0, 16, '#000000');
        foreach ($palette as $row) {
            $index = (int)$row['slot_index'];
            if ($index >= 0 && $index < 16) {
                $colors[$index] = $row['color'];
            }
        }
        
        echo json_encode(['success' => true, 'colors' => $colors]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// POST: カラーパレットを更新
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF check
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!CsrfProtection::validateToken($input['csrf_token'] ?? '')) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
        exit;
    }
    
    try {
        $slotIndex = (int)($input['slot_index'] ?? -1);
        $color = strtoupper($input['color'] ?? '');
        
        if ($slotIndex < 0 || $slotIndex >= 16) {
            throw new Exception('Invalid slot index');
        }
        
        if (!preg_match('/^#[0-9A-F]{6}$/', $color)) {
            throw new Exception('Invalid color format');
        }
        
        // Get driver type
        $driver = $db->getAttribute(PDO::ATTR_DRIVER_NAME);
        
        if ($driver === 'sqlite') {
            $stmt = $db->prepare("
                INSERT INTO color_palettes (user_id, slot_index, color, updated_at)
                VALUES (NULL, ?, ?, datetime('now'))
                ON CONFLICT(user_id, slot_index) 
                DO UPDATE SET color = ?, updated_at = datetime('now')
            ");
            $stmt->execute([$slotIndex, $color, $color]);
        } else {
            // MySQL
            $stmt = $db->prepare("
                INSERT INTO color_palettes (user_id, slot_index, color, updated_at)
                VALUES (NULL, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE color = ?, updated_at = NOW()
            ");
            $stmt->execute([$slotIndex, $color, $color]);
        }
        
        echo json_encode(['success' => true]);
        
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

http_response_code(405);
echo json_encode(['success' => false, 'error' => 'Method not allowed']);
