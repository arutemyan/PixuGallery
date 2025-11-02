<?php

/**
 * マイグレーション 005: view_countsテーブルにpost_typeカラムを追加
 *
 * - post_typeカラム追加（0=single, 1=group）
 * - 既存データは全てsingle（post_type=0）として扱う
 * - ユニークキー制約を(post_id, post_type)に変更
 */

return [
    'name' => 'add_post_type_to_view_counts',

    'up' => function (PDO $db) {
        $helper = \App\Database\DatabaseHelper::class;
        $driver = $helper::getDriver($db);
        $intType = $helper::getIntegerType($db);

        error_log("Migration 005: Starting migration for driver: {$driver}");

        // SQLiteの場合はCountersConnection::initializeSchema()が処理するためスキップ
        if ($driver === 'sqlite') {
            error_log("Migration 005: SQLite detected - migration handled by CountersConnection");
            error_log("Migration 005: Skipping (counters.db is managed separately)");
            return;
        }

        // MySQL/PostgreSQLの場合のみ処理
        error_log("Migration 005: Processing MySQL/PostgreSQL migration");

        // post_typeカラムの存在確認
        $hasPostType = false;
        try {
            if ($driver === 'mysql') {
                $stmt = $db->query("SHOW COLUMNS FROM view_counts LIKE 'post_type'");
                $hasPostType = $stmt->fetch() !== false;
            } elseif ($driver === 'postgresql') {
                $stmt = $db->query("
                    SELECT column_name
                    FROM information_schema.columns
                    WHERE table_name = 'view_counts' AND column_name = 'post_type'
                ");
                $hasPostType = $stmt->fetch() !== false;
            }
        } catch (PDOException $e) {
            error_log("Migration 005: Error checking post_type column: " . $e->getMessage());
        }

        if ($hasPostType) {
            error_log("Migration 005: post_type column already exists, skipping");
            return;
        }

        // post_typeカラムを追加
        try {
            error_log("Migration 005: Adding post_type column");
            $db->exec("ALTER TABLE view_counts ADD COLUMN post_type {$intType} DEFAULT 0 NOT NULL");
            error_log("Migration 005: Successfully added post_type column");
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'Duplicate column') !== false ||
                strpos($e->getMessage(), 'already exists') !== false) {
                error_log("Migration 005: post_type column already exists (ignored)");
            } else {
                error_log("Migration 005: Failed to add post_type column: " . $e->getMessage());
                throw $e;
            }
        }

        // 主キー制約の変更
        try {
            if ($driver === 'mysql') {
                error_log("Migration 005: Updating MySQL primary key");

                // 既存の主キーを確認
                $stmt = $db->query("SHOW KEYS FROM view_counts WHERE Key_name = 'PRIMARY'");
                $primaryKeys = $stmt->fetchAll();

                // 主キーが(post_id, post_type)でない場合のみ変更
                $needsUpdate = true;
                if (count($primaryKeys) === 2) {
                    $keyColumns = array_column($primaryKeys, 'Column_name');
                    if (in_array('post_id', $keyColumns) && in_array('post_type', $keyColumns)) {
                        $needsUpdate = false;
                        error_log("Migration 005: Primary key already set to (post_id, post_type)");
                    }
                }

                if ($needsUpdate) {
                    $db->exec("ALTER TABLE view_counts DROP PRIMARY KEY");
                    error_log("Migration 005: Dropped old primary key");
                    $db->exec("ALTER TABLE view_counts ADD PRIMARY KEY (post_id, post_type)");
                    error_log("Migration 005: Added new composite primary key");
                }

            } elseif ($driver === 'postgresql') {
                error_log("Migration 005: Updating PostgreSQL primary key");

                // 既存の主キーを確認
                $stmt = $db->query("
                    SELECT kcu.column_name
                    FROM information_schema.table_constraints tc
                    JOIN information_schema.key_column_usage kcu
                        ON tc.constraint_name = kcu.constraint_name
                    WHERE tc.table_name = 'view_counts'
                        AND tc.constraint_type = 'PRIMARY KEY'
                ");
                $primaryKeys = $stmt->fetchAll();

                // 主キーが(post_id, post_type)でない場合のみ変更
                $needsUpdate = true;
                if (count($primaryKeys) === 2) {
                    $keyColumns = array_column($primaryKeys, 'column_name');
                    if (in_array('post_id', $keyColumns) && in_array('post_type', $keyColumns)) {
                        $needsUpdate = false;
                        error_log("Migration 005: Primary key already set to (post_id, post_type)");
                    }
                }

                if ($needsUpdate) {
                    $db->exec("ALTER TABLE view_counts DROP CONSTRAINT IF EXISTS view_counts_pkey");
                    error_log("Migration 005: Dropped old primary key constraint");
                    $db->exec("ALTER TABLE view_counts ADD PRIMARY KEY (post_id, post_type)");
                    error_log("Migration 005: Added new composite primary key");
                }
            }

            error_log("Migration 005: Successfully updated primary key to (post_id, post_type)");

        } catch (PDOException $e) {
            // 既に複合キーの場合はエラーを無視
            if (strpos($e->getMessage(), 'already exists') !== false ||
                strpos($e->getMessage(), 'Duplicate') !== false ||
                strpos($e->getMessage(), 'Multiple primary key') !== false) {
                error_log("Migration 005: Primary key already exists (ignored): " . $e->getMessage());
            } else {
                error_log("Migration 005: Failed to update primary key: " . $e->getMessage());
                throw $e;
            }
        }

        error_log("Migration 005: Migration completed successfully");
    }
];
