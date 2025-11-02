<?php

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Database\Connection;

try {
    echo "=== Running Migrations ===\n\n";

    $db = Connection::getInstance();
    $runner = new \App\Database\MigrationRunner($db);

    $results = $runner->run();

    if (empty($results)) {
        echo "No pending migrations.\n";
    } else {
        echo "Executed migrations:\n";
        foreach ($results as $result) {
            $status = $result['status'] === 'success' ? '✓' : '✗';
            echo sprintf(
                "  %s Migration %03d: %s\n",
                $status,
                $result['version'],
                $result['name']
            );

            if ($result['status'] === 'error') {
                echo "    Error: " . $result['error'] . "\n";
            }
        }
    }

    echo "\n=== Migration Summary ===\n";
    $executed = $runner->getExecutedMigrationDetails();
    echo "Total executed migrations: " . count($executed) . "\n\n";

    foreach ($executed as $migration) {
        echo sprintf(
            "  %03d: %s (executed at: %s)\n",
            $migration['version'],
            $migration['name'],
            $migration['executed_at']
        );
    }

    echo "\n✓ Migration completed successfully.\n";

} catch (Exception $e) {
    echo "\n✗ Migration failed: " . $e->getMessage() . "\n";
    echo "\nStack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
