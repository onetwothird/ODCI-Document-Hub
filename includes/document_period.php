<?php
/**
 * ODCI - Shared academic period and stored-upload path helpers
 * ----------------------------------------------------------------------------
 * WHY THIS EXISTS
 *   A department upload (roles/user/handlers/upload_handler.php) stores the
 *   academic period as a range string, e.g. '2026-2027', while older rows and
 *   document_requirements store only the starting year, e.g. '2025'. Every
 *   report previously compared `files.academic_year` against a plain integer
 *   year, so uploads were silently dropped from the admin document tracker.
 *
 *   The helpers below are the single source of truth for converting between the
 *   representations used across the app, plus a resolver for the relative
 *   `files.file_path` values, which are rooted at `roles/` for department
 *   uploads but at the project root for older ones.
 */

if (!defined('ODCI_APP_ROOT')) {
    define('ODCI_APP_ROOT', dirname(__DIR__));
}

/** Normalise any semester representation to the `files.semester` enum value. */
function odci_semester_column($semester): string
{
    $normalized = strtolower(trim((string)$semester));
    $second = ['2', '2nd', 'second', '2nd semester', 'second semester', 'semester 2'];

    return in_array($normalized, $second, true) ? 'second' : 'first';
}

/** 'first' => 'First Semester' */
function odci_semester_label($semester): string
{
    return odci_semester_column($semester) === 'second' ? 'Second Semester' : 'First Semester';
}

/** 'first' => '1st Semester' (the format used by document_requirements) */
function odci_semester_ordinal($semester): string
{
    return odci_semester_column($semester) === 'second' ? '2nd Semester' : '1st Semester';
}

/**
 * Reduce any stored academic year to its starting year.
 * Accepts '2026', 2026, '2026-2027' and returns 0 when nothing parses.
 */
function odci_academic_year_start($value): int
{
    if ($value === null || is_array($value)) {
        return 0;
    }

    $value = trim((string)$value);
    if ($value === '') {
        return 0;
    }

    if (preg_match('/^(\d{4})(?:\s*-\s*(\d{4}))?$/', $value, $matches)) {
        return (int)$matches[1];
    }

    return 0;
}

/** 2026 => '2026-2027' */
function odci_academic_year_range(int $startYear): string
{
    return $startYear . '-' . ($startYear + 1);
}

/** Build the GET/label value used by the tracker period filter. */
function odci_period_key($startYear, $semester): string
{
    return odci_semester_ordinal($semester) . ' AY ' . odci_academic_year_range((int)$startYear);
}

/**
 * Parse a period filter value back into its parts.
 * Accepts '1st Semester AY 2026-2027', '2026-2027', '2026', 'first'.
 */
function odci_period_parse($value): array
{
    $value = trim((string)$value);

    $semester = $value;
    $startYear = 0;

    if (preg_match('/^(.*?)\s+AY\s+(\d{4})(?:\s*-\s*\d{4})?$/i', $value, $matches)) {
        $semester = $matches[1];
        $startYear = (int)$matches[2];
    } else {
        $startYear = odci_academic_year_start($value);
    }

    return [
        'start_year' => $startYear,
        'semester' => odci_semester_column($semester),
        'label' => $startYear > 0 ? odci_period_key($startYear, $semester) : ''
    ];
}

/** SQL expression projecting the starting year of a stored academic year. */
function odci_academic_year_expr(string $column = 'f.academic_year'): string
{
    return "CAST(LEFT(TRIM($column), 4) AS UNSIGNED)";
}

/**
 * SQL predicate matching `files.academic_year` regardless of whether the row
 * stores the bare starting year or the 'YYYY-YYYY' range.
 */
function odci_academic_year_sql(string $column = 'f.academic_year'): string
{
    return odci_academic_year_expr($column) . ' = ?';
}

/**
 * Resolve a stored `files.file_path` to an absolute path on disk.
 * Department uploads are written under roles/uploads, older rows under the
 * project-root uploads directory, and some legacy rows were persisted with
 * leading '../' segments.
 */
function odci_resolve_upload_path($storedPath): ?string
{
    if (!is_string($storedPath)) {
        return null;
    }

    $normalized = str_replace('\\', '/', trim($storedPath));
    $normalized = preg_replace('#^(\.\./)+#', '', $normalized);
    $normalized = ltrim($normalized, '/');

    if ($normalized === '' || strpos($normalized, '..') !== false) {
        return null;
    }

    $relative = str_replace('/', DIRECTORY_SEPARATOR, $normalized);
    $candidates = [
        ODCI_APP_ROOT . DIRECTORY_SEPARATOR . $relative,
        ODCI_APP_ROOT . DIRECTORY_SEPARATOR . 'roles' . DIRECTORY_SEPARATOR . $relative,
    ];

    foreach ($candidates as $candidate) {
        if (is_file($candidate)) {
            return $candidate;
        }
    }

    return null;
}

/** Folder category key => canonical document type label used by the tracker. */
function odci_category_to_document_type(): array
{
    return [
        'ipcr_accomplishment' => 'IPCR Accomplishment',
        'ipcr_target' => 'IPCR Target',
        'workload' => 'Workload',
        'course_syllabus' => 'Course Syllabus',
        'syllabus_acceptance' => 'Course Syllabus Acceptance Form',
        'exam' => 'Exam',
        'tos' => 'TOS',
        'class_record' => 'Class Record',
        'grading_sheet' => 'Grading Sheets',
        'attendance_sheet' => 'Attendance Sheet',
        'stakeholder_feedback' => "Stakeholder's Feedback Form w/ Summary",
        'consultation' => 'Consultation',
        'lecture' => 'Lecture',
        'activities' => 'Activities',
        'exam_acknowledgement' => 'CEIT-QF-03 Discussion Form',
        'consultation_log' => 'Others',
    ];
}

/** Canonical document type label => folder category key. */
function odci_document_type_to_category(): array
{
    return array_flip(odci_category_to_document_type());
}

/** The document types tracked when a period has no document_requirements rows. */
function odci_default_document_types(): array
{
    $types = array_values(odci_category_to_document_type());
    sort($types);

    return $types;
}

/**
 * Resolve a stored `users.profile_image` to a browser-loadable URL.
 * ----------------------------------------------------------------------------
 * WHY THIS EXISTS
 *   `users.profile_image` is stored project-root relative, e.g.
 *   'uploads/profiles/profile_30_1791075938.jpg'. Every caller lives at a
 *   different depth (roles/admin/*.php is two levels down, roles/superadmin is
 *   two levels down, shared pages are at the root), so the prefix has to be
 *   derived from the caller's own directory rather than hard-coded - a literal
 *   '../' prefix silently resolved to roles/uploads/... and every avatar fell
 *   back to the logo.
 *
 * @param string|null $dbImagePath Stored value, or null/'' when unset.
 * @param string      $callerDir   __DIR__ of the calling script.
 * @param string      $fallback    Project-root relative fallback image.
 */
function odci_profile_image_url($dbImagePath, string $callerDir, string $fallback = 'img/cvsu-logo.png'): string
{
    $fallback = ltrim(str_replace('\\', '/', $fallback), '/');

    // Depth of the caller below the project root, used to build the web prefix.
    // realpath() first: callers commonly pass __DIR__ . '/..', which does not
    // collapse textually, so the literal string comparison below would miss and
    // every relative path would come back with too many '../' segments.
    $callerDir = realpath($callerDir);
    $callerDir = $callerDir === false
        ? rtrim(str_replace('\\', '/', $callerDir), '/')
        : rtrim(str_replace('\\', '/', $callerDir), '/');
    $root = rtrim(str_replace('\\', '/', realpath(ODCI_APP_ROOT) ?: ODCI_APP_ROOT), '/');

    if ($callerDir === $root) {
        $webPrefix = '';
    } elseif (strpos($callerDir, $root . '/') === 0) {
        $relativeDir = trim(substr($callerDir, strlen($root)), '/');
        $depth = $relativeDir === '' ? 0 : count(explode('/', $relativeDir));
        $webPrefix = str_repeat('../', $depth);
    } else {
        // Caller is outside the project root - fall back to an absolute path.
        $webPrefix = '/';
    }

    $stored = is_string($dbImagePath) ? trim($dbImagePath) : '';
    $stored = preg_replace('#^(\.\./)+#', '', str_replace('\\', '/', $stored));
    $stored = ltrim($stored, '/');

    // Prefer an exact hit under the project root, then under roles/.
    $candidates = [];
    if ($stored !== '' && strpos($stored, '..') === false) {
        $candidates[] = $stored;
        if (stripos($stored, 'uploads/') !== 0) {
            $candidates[] = 'uploads/' . $stored;
        }
    }

    foreach ($candidates as $candidate) {
        foreach ([ODCI_APP_ROOT . '/' . $candidate, ODCI_APP_ROOT . '/roles/' . $candidate] as $absolute) {
            if (is_file($absolute)) {
                return $webPrefix . $candidate . '?v=' . filemtime($absolute);
            }
        }
    }

    return $webPrefix . $fallback;
}

/**
 * The most recent period a user actually uploaded files in.
 * document_requirements still only covers AY 2025, so a calendar-derived period
 * reports 0% completion for everyone. Returns start_year => 0 when the user has
 * never uploaded, so callers can fall back.
 */
function odci_latest_submission_period($pdo, int $userId): array
{
    try {
        $stmt = $pdo->prepare("
            SELECT f.academic_year AS academic_year, f.semester AS semester, MAX(f.uploaded_at) AS latest
            FROM files f
            INNER JOIN folders fo ON f.folder_id = fo.id
            WHERE f.uploaded_by = ?
              AND f.is_deleted = 0
              AND fo.is_deleted = 0
              AND fo.category IS NOT NULL
            GROUP BY f.academic_year, f.semester
            ORDER BY f.academic_year DESC, f.semester DESC, latest DESC
            LIMIT 1
        ");
        $stmt->execute([$userId]);
        $row = $stmt->fetch();

        if (!$row) {
            return ['start_year' => 0, 'semester' => null];
        }

        return [
            'start_year' => odci_academic_year_start($row['academic_year']),
            'semester' => odci_semester_column($row['semester']),
        ];
    } catch (Throwable $e) {
        return ['start_year' => 0, 'semester' => null];
    }
}

/**
 * Required document types for a period, from document_requirements when it has
 * rows for that period, otherwise the canonical category list.
 */
function odci_period_required_document_types($pdo, int $startYear, $semester): array
{
    try {
        $stmt = $pdo->prepare("
            SELECT DISTINCT document_type
            FROM document_requirements
            WHERE academic_year = ?
              AND semester = ?
              AND is_required = 1
            ORDER BY document_type
        ");
        $stmt->execute([(string)$startYear, odci_semester_ordinal($semester)]);
        $types = $stmt->fetchAll(PDO::FETCH_COLUMN);

        return array_values(array_filter($types));
    } catch (Throwable $e) {
        return [];
    }
}

/**
 * document_type => deadline for a period, scoped to the user's department.
 * Types with no deadline keep a null value so callers can distinguish
 * "no deadline set" from "deadline in the future".
 */
function odci_period_deadlines($pdo, int $startYear, $semester, int $userId): array
{
    try {
        $stmt = $pdo->prepare("
            SELECT dr.document_type, MIN(dr.deadline_date) AS deadline_date
            FROM document_requirements dr
            WHERE dr.academic_year = ?
              AND dr.semester = ?
              AND dr.is_required = 1
              AND (dr.department_id IS NULL OR dr.department_id = (
                    SELECT department_id FROM users WHERE id = ?
              ))
            GROUP BY dr.document_type
        ");
        $stmt->execute([(string)$startYear, odci_semester_ordinal($semester), $userId]);

        $deadlines = [];
        foreach ($stmt->fetchAll() as $row) {
            $deadlines[$row['document_type']] = $row['deadline_date'];
        }

        return $deadlines;
    } catch (Throwable $e) {
        return [];
    }
}

/**
 * Length of the unbroken run of consecutive 'Y-m' values ending at the most
 * recent month present in $months. A gap of a single missing month ends the
 * streak - that is what a "streak" means, as opposed to "months with activity".
 */
function odci_count_month_streak(array $months): int
{
    if (empty($months)) {
        return 0;
    }

    $sorted = array_values(array_unique($months));
    rsort($sorted); // 'Y-m' sorts chronologically as a string.

    $streak = 1;
    $previous = $sorted[0];

    for ($i = 1, $count = count($sorted); $i < $count; $i++) {
        $expected = date('Y-m', strtotime($previous . '-01 -1 month'));
        if ($sorted[$i] === $expected) {
            $streak++;
            $previous = $sorted[$i];
        } else {
            break;
        }
    }

    return $streak;
}