#!/usr/bin/env php
<?php
/**
 * Update timelapse_size for existing records
 */

require_once __DIR__ . '/vendor/autoload.php';

use App\Database\Connection;

echo "Updating timelapse sizes for existing records...\n\n";

try {
    $db = Connection::getInstance();

    // Get all records with timelapse_path but timelapse_size = 0 or NULL
    $stmt = $db->query("SELECT id, timelapse_path FROM paint WHERE timelapse_path IS NOT NULL AND timelapse_path != '' AND (timelapse_size IS NULL OR timelapse_size = 0)");
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($records)) {
        echo "No records to update.\n";
        exit(0);
    }

    echo "Found " . count($records) . " records to update.\n\n";

    $updated = 0;
    $failed = 0;

    foreach ($records as $record) {
        $id = $record['id'];
        $timelapsePath = $record['timelapse_path'];

        // Convert path to filesystem path
        $filePath = $_SERVER['DOCUMENT_ROOT'] . $timelapsePath;

        // Also try relative to script directory
        if (!file_exists($filePath)) {
            $filePath = __DIR__ . '/public' . $timelapsePath;
        }

        if (file_exists($filePath)) {
            $fileSize = filesize($filePath);

            $updateStmt = $db->prepare("UPDATE paint SET timelapse_size = :size WHERE id = :id");
            $updateStmt->execute([
                ':size' => $fileSize,
                ':id' => $id
            ]);

            echo sprintf("✓ Updated ID %d: %s (%s)\n",
                $id,
                basename($timelapsePath),
                formatSize($fileSize)
            );
            $updated++;
        } else {
            echo sprintf("✗ File not found for ID %d: %s\n", $id, $filePath);
            $failed++;
        }
    }

    echo "\n";
    echo "Summary:\n";
    echo "  Updated: $updated\n";
    echo "  Failed:  $failed\n";
    echo "  Total:   " . count($records) . "\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}

function formatSize($bytes) {
    if ($bytes == 0) return '0 B';
    $units = ['B', 'KB', 'MB', 'GB'];
    $i = 0;
    while ($bytes >= 1024 && $i < count($units) - 1) {
        $bytes /= 1024;
        $i++;
    }
    return round($bytes, 1) . ' ' . $units[$i];
}
