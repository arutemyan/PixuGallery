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

        // post_typeカラムを追加（デフォルト値0=single）
        try {
            $db->exec("ALTER TABLE view_counts ADD COLUMN post_type {$intType} DEFAULT 0 NOT NULL");
            error_log("Migration 005: Added post_type column to view_counts");
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'Duplicate column') === false &&
                strpos($e->getMessage(), 'already exists') === false) {
                error_log("Migration 005 post_type column error: " . $e->getMessage());
                throw $e;
            }
        }

        // 既存のユニークキー制約を削除して、新しい複合ユニークキーを追加
        // SQLiteの場合はテーブルの再作成が必要
        if ($driver === 'sqlite') {
            // SQLiteの場合：テーブルを再作成
            try {
                // 一時テーブルに既存データをコピー
                $db->exec("CREATE TABLE view_counts_temp AS SELECT * FROM view_counts");

                // 古いテーブルを削除
                $db->exec("DROP TABLE view_counts");

                // 新しいスキーマでテーブルを作成
                $db->exec("
                    CREATE TABLE view_counts (
                        post_id INTEGER NOT NULL,
                        post_type INTEGER DEFAULT 0 NOT NULL,
                        count INTEGER DEFAULT 0,
                        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                        PRIMARY KEY (post_id, post_type)
                    )
                ");

                // データを戻す
                $db->exec("INSERT INTO view_counts (post_id, post_type, count, updated_at)
                          SELECT post_id, post_type, count, updated_at FROM view_counts_temp");

                // 一時テーブルを削除
                $db->exec("DROP TABLE view_counts_temp");

                error_log("Migration 005: Recreated view_counts table with composite primary key (SQLite)");
            } catch (PDOException $e) {
                error_log("Migration 005 SQLite table recreation error: " . $e->getMessage());
                throw $e;
            }
        } else {
            // MySQL/PostgreSQLの場合：制約を操作
            try {
                if ($driver === 'mysql') {
                    // MySQLでは主キーを削除して再作成
                    $db->exec("ALTER TABLE view_counts DROP PRIMARY KEY");
                    $db->exec("ALTER TABLE view_counts ADD PRIMARY KEY (post_id, post_type)");
                } elseif ($driver === 'postgresql') {
                    // PostgreSQLの場合
                    $db->exec("ALTER TABLE view_counts DROP CONSTRAINT IF EXISTS view_counts_pkey");
                    $db->exec("ALTER TABLE view_counts ADD PRIMARY KEY (post_id, post_type)");
                }
                error_log("Migration 005: Updated primary key to (post_id, post_type)");
            } catch (PDOException $e) {
                error_log("Migration 005 primary key update error: " . $e->getMessage());
                // 既に複合キーの場合はエラーを無視
                if (strpos($e->getMessage(), 'already exists') === false &&
                    strpos($e->getMessage(), 'Duplicate') === false) {
                    throw $e;
                }
            }
        }

        error_log("Migration 005: Successfully added post_type column to view_counts");
    }
];
