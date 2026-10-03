-- Database: myd_db
-- File Management System for CVSU Naic ODCI

-- =====================================================
-- 1. DEPARTMENTS TABLE
-- =====================================================
CREATE TABLE departments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    code VARCHAR(10) NOT NULL UNIQUE,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    icon VARCHAR(50) DEFAULT 'bxs-building',
    color VARCHAR(7) DEFAULT '#6b7280',
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Insert default departments
INSERT INTO departments (code, name, description, icon, color) VALUES
('TED', 'Teacher Education Department', 'Handles teacher preparation and education programs', 'bxs-graduation', '#f59e0b'),
('MD', 'Management Department', 'Business and management programs', 'bxs-business', '#1e40af'),
('FASD', 'Fisheries and Aquatic Science Department', 'Marine and aquatic sciences', 'bx-water', '#0284c7'),
('ASD', 'Arts and Science Department', 'Liberal arts and science programs', 'bxs-palette', '#d946ef'),
('ITD', 'Information Technology Department', 'Computer science and IT programs', 'bxs-chip', '#0f766e'),
('NSTP', 'National Service Training Program', 'Civic and military training', 'bxs-user-check', '#22c55e'),
('OTHER', 'Other Files', 'Miscellaneous and general files', 'bxs-file', '#6b7280');

-- =====================================================
-- 2. DOCUMENT CATEGORIES TABLE
-- =====================================================
CREATE TABLE document_categories (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    code VARCHAR(50) NOT NULL UNIQUE,
    description TEXT,
    icon VARCHAR(50) DEFAULT 'bxs-file',
    color VARCHAR(7) DEFAULT '#6b7280',
    sort_order INT DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Insert school document categories
INSERT INTO document_categories (name, code, description, icon, color, sort_order) VALUES
('IPCR Accomplishments', 'IPCR_ACCOMP', 'Individual Performance Commitment and Review Accomplishments', 'bxs-trophy', '#10b981', 1),
('IPCR Target', 'IPCR_TARGET', 'Individual Performance Commitment and Review Targets', 'bxs-target', '#f59e0b', 2),
('Workload', 'WORKLOAD', 'Faculty workload assignments and schedules', 'bxs-time', '#3b82f6', 3),
('Course Syllabus', 'COURSE_SYLLABUS', 'Course syllabi and curriculum guides', 'bxs-book', '#8b5cf6', 4),
('Course Syllabus Acceptance Form', 'SYLLABUS_ACCEPT', 'Syllabus acceptance and approval forms', 'bxs-check-square', '#06b6d4', 5),
('Exam TOS', 'EXAM_TOS', 'Table of Specifications for examinations', 'bxs-grid', '#ef4444', 6),
('Class Record', 'CLASS_RECORD', 'Student class records and grades', 'bxs-spreadsheet', '#84cc16', 7),
('Grading Sheets', 'GRADING_SHEETS', 'Grade computation and recording sheets', 'bxs-calculator', '#f97316', 8),
('Attendance Sheet', 'ATTENDANCE', 'Student attendance records', 'bxs-user-check', '#14b8a6', 9),
('Stakeholder Feedback Form', 'STAKEHOLDER_FEEDBACK', 'Feedback forms from stakeholders with summary', 'bxs-comment-detail', '#a855f7', 10),
('Consultation Activities', 'CONSULTATION', 'Faculty consultation and guidance activities', 'bxs-conversation', '#ec4899', 11),
('Lecture Activities', 'LECTURE_ACT', 'Teaching and lecture activity records', 'bxs-chalkboard', '#6366f1', 12),
('CEIT-QF-03', 'CEIT_QF03', 'CEIT Quality Form 03 documents', 'bxs-file-doc', '#64748b', 13),
('Discussion of Examination', 'EXAM_DISCUSSION', 'Examination discussion and review materials', 'bxs-chat', '#0ea5e9', 14),
('Acknowledgement Receipt Form', 'ACK_RECEIPT', 'Acknowledgement and receipt forms', 'bxs-receipt', '#22c55e', 15),
('Research Papers', 'RESEARCH', 'Research documents and publications', 'bxs-search', '#dc2626', 16),
('Guidelines', 'GUIDELINES', 'Institutional guidelines and policies', 'bxs-info-circle', '#7c3aed', 17),
('Reports', 'REPORTS', 'Various institutional reports', 'bxs-report', '#059669', 18),
('Other Documents', 'OTHER_DOCS', 'Miscellaneous documents', 'bxs-file-blank', '#6b7280', 99);

-- =====================================================
-- 3. FOLDERS TABLE (Semesters within Departments)
-- =====================================================
CREATE TABLE folders (
    id INT PRIMARY KEY AUTO_INCREMENT,
    department_id INT NOT NULL,
    semester ENUM('first', 'second') NOT NULL,
    academic_year VARCHAR(20) DEFAULT '2024-2025',
    name VARCHAR(255) NOT NULL,
    description TEXT,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    is_active BOOLEAN DEFAULT TRUE,
    
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_dept_semester_year (department_id, semester, academic_year)
);

-- =====================================================
-- 4. FILES TABLE
-- =====================================================
CREATE TABLE files (
    id INT PRIMARY KEY AUTO_INCREMENT,
    folder_id INT NOT NULL,
    category_id INT,
    original_filename VARCHAR(255) NOT NULL,
    stored_filename VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_size BIGINT NOT NULL,
    file_type VARCHAR(100) NOT NULL,
    mime_type VARCHAR(100) NOT NULL,
    description TEXT,
    uploaded_by INT NOT NULL,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    download_count INT DEFAULT 0,
    last_downloaded_at TIMESTAMP NULL,
    is_deleted BOOLEAN DEFAULT FALSE,
    deleted_at TIMESTAMP NULL,
    deleted_by INT NULL,
    
    FOREIGN KEY (folder_id) REFERENCES folders(id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES document_categories(id) ON DELETE SET NULL,
    FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (deleted_by) REFERENCES users(id) ON DELETE SET NULL,
    
    INDEX idx_folder_category (folder_id, category_id),
    INDEX idx_uploaded_by (uploaded_by),
    INDEX idx_file_type (file_type),
    INDEX idx_deleted (is_deleted),
    FULLTEXT(original_filename, description)
);

-- =====================================================
-- 5. FILE TAGS TABLE
-- =====================================================
CREATE TABLE file_tags (
    id INT PRIMARY KEY AUTO_INCREMENT,
    file_id INT NOT NULL,
    tag_name VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (file_id) REFERENCES files(id) ON DELETE CASCADE,
    UNIQUE KEY unique_file_tag (file_id, tag_name),
    INDEX idx_tag_name (tag_name)
);

-- =====================================================
-- 6. FILE VERSIONS TABLE (Optional - for version control)
-- =====================================================
CREATE TABLE file_versions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    file_id INT NOT NULL,
    version_number INT NOT NULL DEFAULT 1,
    stored_filename VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_size BIGINT NOT NULL,
    uploaded_by INT NOT NULL,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    version_notes TEXT,
    is_current BOOLEAN DEFAULT FALSE,
    
    FOREIGN KEY (file_id) REFERENCES files(id) ON DELETE CASCADE,
    FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_file_version (file_id, version_number)
);

-- =====================================================
-- 7. FILE ACCESS LOG TABLE (Optional - for tracking)
-- =====================================================
CREATE TABLE file_access_log (
    id INT PRIMARY KEY AUTO_INCREMENT,
    file_id INT NOT NULL,
    user_id INT NOT NULL,
    action ENUM('view', 'download', 'edit', 'delete') NOT NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    accessed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (file_id) REFERENCES files(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_file_access (file_id, accessed_at),
    INDEX idx_user_access (user_id, accessed_at)
);

-- =====================================================
-- 8. DEPARTMENT PERMISSIONS TABLE (Optional)
-- =====================================================
CREATE TABLE department_permissions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    department_id INT NOT NULL,
    permission_level ENUM('read', 'write', 'admin') DEFAULT 'read',
    granted_by INT NOT NULL,
    granted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NULL,
    is_active BOOLEAN DEFAULT TRUE,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE CASCADE,
    FOREIGN KEY (granted_by) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_dept (user_id, department_id)
);

-- =====================================================
-- 9. USEFUL VIEWS
-- =====================================================

-- View for files with full information
CREATE VIEW file_details AS
SELECT 
    f.id,
    f.original_filename,
    f.file_size,
    f.file_type,
    f.description,
    f.uploaded_at,
    f.download_count,
    u.full_name AS uploader_name,
    u.email AS uploader_email,
    fo.semester,
    fo.academic_year,
    d.name AS department_name,
    d.code AS department_code,
    dc.name AS category_name,
    dc.code AS category_code,
    GROUP_CONCAT(ft.tag_name) AS tags
FROM files f
JOIN folders fo ON f.folder_id = fo.id
JOIN departments d ON fo.department_id = d.id
JOIN users u ON f.uploaded_by = u.id
LEFT JOIN document_categories dc ON f.category_id = dc.id
LEFT JOIN file_tags ft ON f.id = ft.file_id
WHERE f.is_deleted = FALSE
GROUP BY f.id;

-- View for department statistics
CREATE VIEW department_stats AS
SELECT 
    d.id,
    d.code,
    d.name,
    COUNT(DISTINCT fo.id) AS total_folders,
    COUNT(DISTINCT f.id) AS total_files,
    COALESCE(SUM(f.file_size), 0) AS total_file_size,
    COUNT(DISTINCT f.uploaded_by) AS unique_uploaders
FROM departments d
LEFT JOIN folders fo ON d.id = fo.department_id AND fo.is_active = TRUE
LEFT JOIN files f ON fo.id = f.folder_id AND f.is_deleted = FALSE
GROUP BY d.id, d.code, d.name;

-- =====================================================
-- 10. SAMPLE DATA (Optional)
-- =====================================================

-- Create sample folders for each department
INSERT INTO folders (department_id, semester, academic_year, name, created_by) VALUES
-- TED folders
(1, 'first', '2024-2025', 'TED First Semester 2024-2025', 1),
(1, 'second', '2024-2025', 'TED Second Semester 2024-2025', 1),
-- MD folders
(2, 'first', '2024-2025', 'MD First Semester 2024-2025', 1),
(2, 'second', '2024-2025', 'MD Second Semester 2024-2025', 1),
-- ITD folders
(5, 'first', '2024-2025', 'ITD First Semester 2024-2025', 1),
(5, 'second', '2024-2025', 'ITD Second Semester 2024-2025', 1);

-- =====================================================
-- 11. INDEXES FOR PERFORMANCE
-- =====================================================
CREATE INDEX idx_files_folder_category ON files(folder_id, category_id, is_deleted);
CREATE INDEX idx_files_uploaded_at ON files(uploaded_at);
CREATE INDEX idx_folders_dept_semester ON folders(department_id, semester, is_active);
CREATE INDEX idx_departments_active ON departments(is_active);

-- =====================================================
-- 12. FUNCTIONS AND PROCEDURES (Optional)
-- =====================================================

-- Function to get file count by department
DELIMITER //
CREATE FUNCTION GetDepartmentFileCount(dept_id INT, semester_filter VARCHAR(10)) 
RETURNS INT READS SQL DATA DETERMINISTIC
BEGIN
    DECLARE file_count INT DEFAULT 0;
    
    SELECT COUNT(f.id) INTO file_count
    FROM files f
    JOIN folders fo ON f.folder_id = fo.id
    WHERE fo.department_id = dept_id
    AND f.is_deleted = FALSE
    AND (semester_filter IS NULL OR fo.semester = semester_filter);
    
    RETURN file_count;
END //
DELIMITER ;

-- Procedure to soft delete files
DELIMITER //
CREATE PROCEDURE SoftDeleteFile(IN file_id INT, IN user_id INT)
BEGIN
    UPDATE files 
    SET is_deleted = TRUE, 
        deleted_at = CURRENT_TIMESTAMP, 
        deleted_by = user_id
    WHERE id = file_id;
END //
DELIMITER ;