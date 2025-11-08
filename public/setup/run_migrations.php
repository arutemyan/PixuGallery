<?php

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

// Run migrations using an isolated PDO connection and a file lock to avoid concurrent
// processes opening the same SQLite file and causing 'database is locked'.
try {
    echo "=== Running Migrations ===\n\n";

    // Acquire a cross-process lock so only one migration runner touches the DB at a time.
    $lockFile = sys_get_temp_dir() . '/photo_site_migrations.lock';
    $lockFp = fopen($lockFile, 'c');
    if ($lockFp === false) {
        throw new Exception('Could not open migration lock file: ' . $lockFile);
    }

    // Block until exclusive lock is acquired
    if (!flock($lockFp, LOCK_EX)) {
        throw new Exception('Could not acquire migration lock');
    }

    // Load config to determine DB settings
    $configPath = __DIR__ . '/../../config/config.php';
    $config = file_exists($configPath) ? require $configPath : [];

    // Allow CI to override DB settings via environment variables (TEST_DB_*)
    $envDriver = getenv('TEST_DB_DRIVER');
    if ($envDriver !== false && $envDriver !== null && $envDriver !== '') {
        $config['database']['driver'] = $envDriver;
        // For sqlite, TEST_DB_DSN may be like 'sqlite::memory:' or 'sqlite:/path/to.db'
        $envDsn = getenv('TEST_DB_DSN');
        if ($envDriver === 'sqlite' && $envDsn) {
            if (preg_match('#^sqlite:(.*)$#', $envDsn, $m)) {
                $config['database']['sqlite']['gallery']['path'] = $m[1];
            } else {
                $config['database']['sqlite']['gallery']['path'] = $envDsn;
            }
        }
        if ($envDriver === 'mysql' || $envDriver === 'postgresql') {
            $svc = $envDriver === 'mysql' ? 'mysql' : 'postgresql';
            $envHost = getenv('TEST_DB_HOST');
            $envPort = getenv('TEST_DB_PORT');
            $envName = getenv('TEST_DB_NAME');
            $envUser = getenv('TEST_DB_USER');
            $envPass = getenv('TEST_DB_PASS');
            if ($envHost) $config['database'][$svc]['host'] = $envHost;
            if ($envPort) $config['database'][$svc]['port'] = (int)$envPort;
            if ($envName) $config['database'][$svc]['database'] = $envName;
            if ($envUser) $config['database'][$svc]['username'] = $envUser;
            if ($envPass) $config['database'][$svc]['password'] = $envPass;
        }
    }

    $driver = $config['database']['driver'] ?? 'sqlite';
    if ($driver === 'sqlite') {
        $dbPath = $config['database']['sqlite']['gallery']['path'] ?? __DIR__ . '/../../data/gallery.db';

        // Ensure directory exists
        $dbDir = dirname($dbPath);
        if (!is_dir($dbDir)) {
            mkdir($dbDir, 0755, true);
        }

        // Tell the application's Connection to use this path, then obtain the instance
        \App\Database\Connection::setDatabasePath($dbPath);
        $db = \App\Database\Connection::getInstance();
    } else {
        // Non-sqlite: use the application's connection
        $db = \App\Database\Connection::getInstance();
    }

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

    // Release lock
    flock($lockFp, LOCK_UN);
    fclose($lockFp);

} catch (Exception $e) {
    // Ensure lock is released on error as well
    if (isset($lockFp) && is_resource($lockFp)) {
        flock($lockFp, LOCK_UN);
        fclose($lockFp);
    }

    echo "\n✗ Migration failed: " . $e->getMessage() . "\n";
    echo "\nStack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
