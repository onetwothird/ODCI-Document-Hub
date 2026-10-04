-- ---------------------------------------------------------------------------
-- 002 - give `folders` a real status column
--
-- Why: roles/admin/folders.php has always rendered a status badge and offered a
-- "Change Status" action, but the column it wrote to was never created. The page
-- read $folder['folder_status'] (always null, so every badge silently fell
-- through to "Active") and the update ran
--
--     UPDATE folders SET folder_status = ? WHERE id = ?
--
-- which fails with "Unknown column 'folder_status' in 'field list'". That is why
-- the menu item was left as a dead <a href="#">.
--
-- Idempotent: safe to run more than once. Re-running is a no-op because the
-- information_schema guard turns the ALTER into a no-op statement.
--
-- The no-op branch deliberately uses SET rather than SELECT 'message'. An
-- EXECUTE that returns a result set leaves an unread cursor, and the next
-- DEALLOCATE then fails with "Cannot execute queries while other unbuffered
-- queries are active". The mysql client swallows that; PDO does not, so the
-- script is written to be safe through either runner.
--
-- Run with:  mysql -u <user> -p <database> < database/migrations/002_folders_add_status.sql
-- ---------------------------------------------------------------------------

SET @db := DATABASE();

-- 1. Add the column only if it is not already there.
SET @sql := IF(
    EXISTS (
        SELECT 1
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = @db
          AND TABLE_NAME   = 'folders'
          AND COLUMN_NAME  = 'folder_status'
    ),
    'SET @migration_note := ''folders.folder_status already exists - nothing to do''',
    'ALTER TABLE `folders`
         ADD COLUMN `folder_status` ENUM(''active'',''archived'',''hidden'')
         NOT NULL DEFAULT ''active''
         AFTER `folder_type`'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 2. Backfill. Only rows where the value is NULL or outside the enum can occur
--    here, because the column is NOT NULL DEFAULT ''active''. Written as a
--    general repair anyway so the script also fixes a table that was altered by
--    hand with a nullable column.
UPDATE `folders`
   SET `folder_status` = 'active'
 WHERE `folder_status` IS NULL
    OR `folder_status` NOT IN ('active', 'archived', 'hidden');