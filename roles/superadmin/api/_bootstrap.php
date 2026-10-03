<?php
/**
 * Shared bootstrap for the superadmin file-manager JSON API.
 *
 * The superadmin "Files" page is a client-side application that talks to
 * api/files.php, api/folders.php and api/departments.php. Those endpoints were
 * referenced by the page but never written, which left every action on that page
 * (list, upload, download, delete, share, export, ...) failing with a 404.
 *
 * This file centralises authentication, JSON encoding and small helpers so the
 * three endpoints stay consistent.
 */

require_once __DIR__ . '/../../../includes/config.php';
require_once __DIR__ . '/../../../includes/auth_check.php';

/** Send a JSON response and stop. */
function api_json($payload, int $status = 200): void
{
    if (!headers_sent()) {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('X-Content-Type-Options: nos/niff');
    }
    echo json_encode($payload);
    exit;
}

/** Send a JSON success envelope. */
function api_ok(array $data = [], string $message = 'OK'): void
{
    api_json(['success' => true, 'message' => $message, 'data' => $data]);
}

/** Send a JSON failure envelope. */
function api_fail(string $message, int $status = 400): void
{
    api_json(['success' => false, 'message' => $message], $status);
}

/**
 * Read the request body. The page posts either JSON (most actions) or
 * multipart form data (uploads), so support both.
 */
function api_input(): array
{
    $raw = file_get_contents('php://input');

    if ($raw !== false && $raw !== '') {
        // Strip a UTF-8 BOM if present. Some clients (and Windows tooling that
        // writes JSON files) emit one, and json_decode() rejects it outright -
        // which would otherwise make every action look like "missing id".
        $raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw);

        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            return $decoded;
        }
    }

    return $_POST;
}

/** Resolve an absolute path for a stored file, refusing directory traversal. */
function api_safe_realpath(string $relativePath): ?string
{
    // __DIR__ is <ODCI>/roles/superadmin/api, so the project root is three levels up.
    $root = realpath(__DIR__ . '/../../..');
    if ($root === false || $relativePath === '') {
        return null;
    }

    // Stored paths are relative to the project root (e.g. "uploads/doc.pdf").
    $candidate = realpath($root . DIRECTORY_SEPARATOR . ltrim($relativePath, '/\\'));
    if ($candidate === false) {
        return null;
    }

    // Must resolve inside the project root.
    $prefix = $root . DIRECTORY_SEPARATOR;
    if (strncmp($candidate, $prefix, strlen($prefix)) !== 0) {
        return null;
    }

    return $candidate;
}

/** Human readable byte size, used by CSV exports. */
function api_format_size($bytes): string
{
    $bytes = (float)($bytes ?: 0);
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $i = 0;
    while ($bytes >= 1024 && $i < count($units) - 1) {
        $bytes /= 1024;
        $i++;
    }
    return round($bytes, $i === 0 ? 0 : 2) . ' ' . $units[$i];
}

/**
 * Authorise the current request for the superadmin file manager.
 * Terminates the request with a JSON error when access is denied.
 */
function api_require_superadmin(): array
{
    $user = requireSuperAdmin();
    if (!$user) {
        api_fail('Authentication required', 401);
    }
    return $user;
}
