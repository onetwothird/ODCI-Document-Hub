<?php
/**
 * ODCI - Automatic schema repair (self-healing bootstrap)
 * ----------------------------------------------------------------------------
 * WHY THIS EXISTS
 *   Several parts of this application were written against database columns that
 *   were never actually created, because the intended migration statements were
 *   drafted in "dinagdag sa table.txt" but never executed. That produced fatal
 *   errors such as:
 *
 *     SQLSTATE[42S22] Unknown column 'academic_year' in 'field list'
 *     SQLSTATE[42S22] Unknown column 'folder_type'  in 'field list'
 *
 *   Running the SQL migration by hand is not enough: if the database is ever
 *   restored or re-imported from a dump, the columns disappear again and the
 *   application breaks with no obvious cause. This module therefore verifies the
 *   required schema on bootstrap and repairs it automatically.
 *
 * DESIGN NOTES
 *   - Idempotent: every object is created only when missing.
 *   - Cheap: a marker file short-circuits the check, so the common request path
 *     performs a single file stat and no database queries at all.
 *   - Fail-safe: all work happens inside try/catch and never throws, so a
 *     permissions problem or a locked database can never take the site down.
 *   - The authoritative, human-readable version of this migration lives in
 *     database/migrations/001_repair_missing_schema.sql
 */

if (!defined('ODCI_SCHEMA_MARKER')) {
    define('ODCI_SCHEMA_MARKER', sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'odci_schema_checked');
    // Re-verify at most this often (seconds). Keeps self-heal fast after a
    // database restore while avoiding per-request database work.
    define('ODCI_SCHEMA_TTL', 300);
}

/**
 * Column definitions required by the application code.
 * table => [column => definition]
 */
function odci_required_schema(): array
{
    return [
        'files' => [
            'academic_year' => "VARCHAR(10) NULL DEFAULT NULL",
            'semester'       => "ENUM('first','second') NOT NULL DEFAULT 'first'",
        ],
        'folders' => [
            'category'     => 'VARCHAR(50) NULL DEFAULT NULL',
            'folder_type'  => "ENUM('category','custom','system') NOT NULL DEFAULT 'custom'",
            'access_count' => 'INT NOT NULL DEFAULT 0',
            'is_favorite'  => 'TINYINT(1) NOT NULL DEFAULT 0',
        ],
    ];
}

/**
 * Ensure the required columns/tables exist. Safe to call on every request.
 */
function odci_ensure_schema(PDO $pdo): void
{
    // Fast path: recently verified, do nothing.
    if (is_file(ODCI_SCHEMA_MARKER)) {
        $age = time() - @filemtime(ODCI_SCHEMA_MARKER);
        if ($age >= 0 && $age < ODCI_SCHEMA_TTL) {
            return;
        }
    }

    try {
        $dbName = $pdo->query('SELECT DATABASE()')->fetchColumn();
        if (empty($dbName)) {
            return; // No database selected; nothing sensible to do.
        }

        // Discover which required columns are actually missing, per table.
        $existing = [];
        foreach (odci_required_schema() as $table => $columns) {
            $cols    = array_keys($columns);
            $inList  = implode(',', array_fill(0, count($cols), '?'));
            $stmt    = $pdo->prepare(
                "SELECT COLUMN_NAME FROM information_schema.COLUMNS
                  WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME IN ($inList)"
            );
            $stmt->execute(array_merge([$dbName, $table], $cols));

            foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $col) {
                $existing[$table][$col] = true;
            }
        }

        foreach (odci_required_schema() as $table => $columns) {
            foreach ($columns as $column => $definition) {
                if (!isset($existing[$table][$column])) {
                    // Identifiers are hard-coded above, not user input.
                    $pdo->exec("ALTER TABLE `$table` ADD COLUMN `$column` $definition");
                    error_log("ODCI schema repair: added `$table`.`$column`");
                }
            }
        }

        // Download audit trail used by roles/user/handlers/download_file.php
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS `file_downloads` (
                `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `file_id`       INT(11)         NOT NULL,
                `user_id`       INT(11)         NOT NULL,
                `downloaded_at` TIMESTAMP       NULL DEFAULT CURRENT_TIMESTAMP,
                `user_ip`       VARCHAR(45)     DEFAULT NULL,
                PRIMARY KEY (`id`),
                KEY `idx_file_downloads_file` (`file_id`),
                KEY `idx_file_downloads_user` (`user_id`),
                KEY `idx_file_downloads_date` (`downloaded_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        // Backfill the derived academic year for pre-existing rows so reports
        // are not blank for historical uploads.
        $pdo->exec('UPDATE `files` SET `academic_year` = YEAR(`uploaded_at`) WHERE `academic_year` IS NULL');

        @touch(ODCI_SCHEMA_MARKER);
    } catch (Throwable $e) {
        // Never let schema maintenance break a page render.
        error_log('ODCI schema repair skipped: ' . $e->getMessage());
    }
}

/**
 * Manually invalidate the cache (call after running the SQL migration by hand).
 */
function odci_reset_schema_cache(): void
{
    if (is_file(ODCI_SCHEMA_MARKER)) {
        @unlink(ODCI_SCHEMA_MARKER);
    }
}
