-- ============================================================================
-- ODCI Migration 001 - Repair missing schema objects
-- ----------------------------------------------------------------------------
-- WHY THIS EXISTS
--   The application code (roles/user/my_reports.php, roles/user/my_files.php,
--   roles/superadmin/folders.php, roles/admin/folders.php,
--   roles/user/handlers/download_file.php) was written against a schema that
--   was never actually migrated into the database. The intended statements were
--   drafted in "dinagdag sa table.txt" but never executed, which produced:
--
--     SQLSTATE[42S22] Unknown column 'academic_year' in 'field list'
--     SQLSTATE[42S22] Unknown column 'folder_type'  in 'field list'
--
--   This migration is IDEMPOTENT: every object is created only when missing,
--   so it is safe to run repeatedly and safe on already-patched databases.
--
--   MySQL does not support "ALTER TABLE ... ADD COLUMN IF NOT EXISTS"
--   (that is MariaDB syntax), so we guard with information_schema lookups.
--
--   Semester convention: the `files` / `folders` subsystem uses the low-cardinality
--   values 'first' / 'second' (see roles/user/my_reports.php filters and the
--   original migration note). The separate document-tracker subsystem
--   (document_files.semester_period / faculty_document_submissions.semester)
--   uses '1st Semester' / '2nd Semester' and is intentionally left untouched.
-- ============================================================================

-- ---------------------------------------------------------------------------
-- Helper: add a column only if it does not already exist
-- ---------------------------------------------------------------------------
DROP PROCEDURE IF EXISTS odci_add_column;
DELIMITER $$
CREATE PROCEDURE odci_add_column(
    IN p_table      VARCHAR(64),
    IN p_column     VARCHAR(64),
    IN p_definition TEXT
)
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME   = p_table
          AND COLUMN_NAME  = p_column
    ) THEN
        SET @ddl = CONCAT('ALTER TABLE `', p_table, '` ADD COLUMN `', p_column, '` ', p_definition);
        PREPARE stmt FROM @ddl;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END$$
DELIMITER ;

-- ---------------------------------------------------------------------------
-- Helper: add an index only if it does not already exist
-- ---------------------------------------------------------------------------
DROP PROCEDURE IF EXISTS odci_add_index;
DELIMITER $$
CREATE PROCEDURE odci_add_index(
    IN p_table VARCHAR(64),
    IN p_index VARCHAR(64),
    IN p_ddl   TEXT
)
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME   = p_table
          AND INDEX_NAME   = p_index
    ) THEN
        SET @ddl = CONCAT('ALTER TABLE `', p_table, '` ADD ', p_ddl);
        PREPARE stmt FROM @ddl;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END$$
DELIMITER ;

-- ===========================================================================
-- 1. files  -- academic year + semester
--    Required by:
--      roles/user/my_reports.php  (SELECT DISTINCT academic_year ... / filters)
--      roles/user/my_files.php    (INSERT INTO files ... academic_year, semester)
-- ===========================================================================
CALL odci_add_column('files', 'academic_year', "VARCHAR(10) NULL DEFAULT NULL COMMENT 'Academic year, e.g. 2026'");
CALL odci_add_column('files', 'semester',       "ENUM('first','second') NOT NULL DEFAULT 'first'");

-- ===========================================================================
-- 2. folders -- category, folder_type, access_count, is_favorite
--    Required by:
--      roles/superadmin/folders.php (stats query, grid/table/tree render, filters)
--      roles/admin/folders.php       (folder_type filter + badge)
--      roles/user/handlers/folder_management.php (toggle_favorite action)
-- ===========================================================================
CALL odci_add_column('folders', 'category',     "VARCHAR(50) NULL DEFAULT NULL");
CALL odci_add_column('folders', 'folder_type', "ENUM('category','custom','system') NOT NULL DEFAULT 'custom'");
CALL odci_add_column('folders', 'access_count', "INT NOT NULL DEFAULT 0");
CALL odci_add_column('folders', 'is_favorite',  "TINYINT(1) NOT NULL DEFAULT 0");

-- ===========================================================================
-- 3. file_downloads -- download audit trail
--    Required by roles/user/handlers/download_file.php
-- ===========================================================================
CREATE TABLE IF NOT EXISTS `file_downloads` (
    `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `file_id`       INT(11)         NOT NULL,
    `user_id`       INT(11)         NOT NULL,
    `downloaded_at` TIMESTAMP       NULL DEFAULT CURRENT_TIMESTAMP,
    `user_ip`       VARCHAR(45)     DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_file_downloads_file` (`file_id`),
    KEY `idx_file_downloads_user` (`user_id`),
    KEY `idx_file_downloads_date` (`downloaded_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===========================================================================
-- 4. Reporting / filtering indexes
-- ===========================================================================
CALL odci_add_index('files', 'idx_files_user_period',
    'INDEX `idx_files_user_period` (`uploaded_by`, `academic_year`, `semester`, `is_deleted`)');
CALL odci_add_index('folders', 'idx_folders_type',
    'INDEX `idx_folders_type` (`folder_type`)');

-- ===========================================================================
-- 5. Backfill historical rows
--    Existing files predate the academic_year column, so they are NULL and the
--    reports page would render empty. Derive the year from uploaded_at, which
--    matches the "YYYY" convention used by the rest of the application.
-- ===========================================================================
UPDATE `files` SET `academic_year` = YEAR(`uploaded_at`) WHERE `academic_year` IS NULL;

-- Seed folder_type for folders that were created before the column existed.
-- Department-scoped folders are treated as 'category' (predefined structure),
-- everything else as 'custom'.
UPDATE `folders` SET `folder_type` = 'category' WHERE `folder_type` = 'custom' AND `department_id` IS NOT NULL;

-- ===========================================================================
-- Cleanup
-- ===========================================================================
DROP PROCEDURE IF EXISTS odci_add_column;
DROP PROCEDURE IF EXISTS odci_add_index;

SELECT 'Migration 001 applied successfully.' AS result;
