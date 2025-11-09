<?php

/**
 * マイグレーション 013: paint テーブルに timelapse_size カラムを追加
 */

return [
    'name' => 'add_timelapse_size_to_paint',

    'up' => function (PDO $db) {
        $helper = \App\Database\DatabaseHelper::class;
        $driver = $helper::getDriver($db);
        $intType = $helper::getIntegerType($db);

        if ($driver === 'sqlite') {
            // SQLite supports ADD COLUMN
            $db->exec("ALTER TABLE paint ADD COLUMN timelapse_size INTEGER DEFAULT 0");
        } else {
            // MySQL / MariaDB / others
            $db->exec("ALTER TABLE paint ADD COLUMN timelapse_size INT DEFAULT 0");
        }

        // Update existing records with actual file sizes
        echo "Updating timelapse sizes for existing records...\n";

        $stmt = $db->query("SELECT id, timelapse_path FROM paint WHERE timelapse_path IS NOT NULL AND timelapse_path != ''");
        $records = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $updated = 0;
        foreach ($records as $record) {
            $id = $record['id'];
            $timelapsePath = $record['timelapse_path'];

            // Try to find the file
            $possiblePaths = [
                $_SERVER['DOCUMENT_ROOT'] . $timelapsePath,
                __DIR__ . '/../../../public' . $timelapsePath,
                __DIR__ . '/../../..' . $timelapsePath
            ];

            $fileSize = 0;
            foreach ($possiblePaths as $filePath) {
                if (file_exists($filePath)) {
                    $fileSize = filesize($filePath);
                    break;
                }
            }

            if ($fileSize > 0) {
                $updateStmt = $db->prepare("UPDATE paint SET timelapse_size = :size WHERE id = :id");
                $updateStmt->execute([':size' => $fileSize, ':id' => $id]);
                $updated++;
            }
        }

        echo "Updated timelapse_size for $updated records.\n";
    }
];
