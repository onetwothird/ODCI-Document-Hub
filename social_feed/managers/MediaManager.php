<?php

class MediaManager
{
    /**
     * Add media attachment to post with new file structure
     */
    public static function addPostMedia($pdo, $postId, $mediaType, $filePath = null, $fileName = null, $originalName = null, $fileSize = null, $mimeType = null, $sortOrder = 0)
    {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO post_media (post_id, media_type, file_path, file_name, original_name, file_size, mime_type, sort_order, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");

            $result = $stmt->execute([
                $postId,
                $mediaType,
                $filePath,
                $fileName,
                $originalName,
                $fileSize,
                $mimeType,
                $sortOrder
            ]);

            if ($result) {
                $mediaId = $pdo->lastInsertId();
                error_log("Media added successfully: ID {$mediaId}, Type: {$mediaType}, Path: {$filePath}");
                return $mediaId;
            }

            return false;
        } catch (Exception $e) {
            error_log("Add post media error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get post media attachments
     */
    public static function getPostMedia($pdo, $postId)
    {
        try {
            $stmt = $pdo->prepare("
                SELECT * FROM post_media 
                WHERE post_id = ? 
                ORDER BY sort_order ASC, created_at ASC
            ");
            $stmt->execute([$postId]);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Get post media error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Create upload directories based on new structure
     * Creates: ODCI/social_feed/uploads/{images,files}/
     */
    public static function createUploadDirectories($baseDir = null)
    {
        if (!$baseDir) {
            // UPDATED: Default to ODCI/social_feed/uploads/
            $baseDir = $_SERVER['DOCUMENT_ROOT'] . '/ODCI/social_feed/uploads/';
        }

        $directories = [
            $baseDir,
            $baseDir . 'images/',
            $baseDir . 'files/'
        ];

        $created = [];
        $errors = [];

        foreach ($directories as $dir) {
            if (!is_dir($dir)) {
                if (mkdir($dir, 0755, true)) {
                    $created[] = $dir;
                    error_log("Created directory: {$dir}");
                } else {
                    $errors[] = "Failed to create directory: {$dir}";
                    error_log("Failed to create directory: {$dir}");
                }
            }
        }

        return [
            'created' => $created,
            'errors' => $errors,
            'success' => empty($errors)
        ];
    }

    /**
     * Get proper upload directory based on media type
     */
    public static function getUploadDirectory($mediaType, $baseDir = null)
    {
        if (!$baseDir) {
            // UPDATED: Use correct base path
            $baseDir = $_SERVER['DOCUMENT_ROOT'] . '/ODCI/social_feed/uploads/';
        }

        switch ($mediaType) {
            case 'image':
                return $baseDir . 'images/';
            case 'file':
                return $baseDir . 'files/';
            default:
                return $baseDir . 'files/'; // Default to files directory
        }
    }

    /**
     * Convert database path to web-accessible URL
     * Database stores: "social_feed/uploads/images/filename.jpg"
     * This converts to: "/ODCI/social_feed/uploads/images/filename.jpg"
     */
    public static function getWebPath($dbPath)
    {
        // Ensure the path starts with a slash for web access and includes ODCI
        if (!empty($dbPath)) {
            if (str_starts_with($dbPath, 'social_feed/')) {
                return '/ODCI/' . $dbPath;
            } elseif (!str_starts_with($dbPath, '/')) {
                return '/ODCI/' . $dbPath;
            }
        }
        return $dbPath;
    }

    /**
     * Validate uploaded file
     */
    public static function validateFile($file, $mediaType)
    {
        $errors = [];

        // Check for upload errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = "Upload error: " . $file['error'];
            return $errors;
        }

        // File size limits
        $maxSizes = [
            'image' => 20 * 1024 * 1024, // 20MB for images
            'file' => 100 * 1024 * 1024  // 100MB for files
        ];

        $maxSize = $maxSizes[$mediaType] ?? $maxSizes['file'];

        if ($file['size'] > $maxSize) {
            $errors[] = "File too large. Maximum size: " . self::formatFileSize($maxSize);
        }

        // MIME type validation
        if ($mediaType === 'image') {
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            if (!in_array($file['type'], $allowedTypes)) {
                $errors[] = "Invalid image type. Allowed: JPEG, PNG, GIF, WebP";
            }
        } else {
            // For files, block image types (should use image upload)
            $imageTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            if (in_array($file['type'], $imageTypes)) {
                $errors[] = "Image files should use the image upload feature";
            }
        }

        return $errors;
    }

    /**
     * Generate unique filename
     */
    public static function generateUniqueFilename($originalName)
    {
        $extension = pathinfo($originalName, PATHINFO_EXTENSION);
        return uniqid() . '_' . time() . '.' . $extension;
    }

    /**
     * Format file size for display
     */
    public static function formatFileSize($bytes)
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, 2) . ' ' . $units[$i];
    }

    /**
     * Delete media file and database record
     */
    public static function deleteMedia($pdo, $mediaId)
    {
        try {
            // Get media info first
            $stmt = $pdo->prepare("SELECT * FROM post_media WHERE id = ?");
            $stmt->execute([$mediaId]);
            $media = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$media) {
                return false;
            }

            // Delete file from filesystem
            $filePath = self::getFullFilePath($media['file_path']);
            if (file_exists($filePath)) {
                unlink($filePath);
                error_log("Deleted file: {$filePath}");
            }

            // Delete from database
            $stmt = $pdo->prepare("DELETE FROM post_media WHERE id = ?");
            $result = $stmt->execute([$mediaId]);

            if ($result) {
                error_log("Deleted media record: ID {$mediaId}");
            }

            return $result;
        } catch (Exception $e) {
            error_log("Delete media error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get full file system path from database path
     * Database stores: "social_feed/uploads/images/filename.jpg"
     * This converts to: "/full/server/path/ODCI/social_feed/uploads/images/filename.jpg"
     */
    private static function getFullFilePath($dbPath)
    {
        // Get the document root (where ODCI is located)
        $documentRoot = $_SERVER['DOCUMENT_ROOT'];
        
        // Remove leading slash if present in dbPath
        $cleanPath = ltrim($dbPath, '/');
        
        // If path doesn't start with ODCI, add it
        if (!str_starts_with($cleanPath, 'ODCI/')) {
            $cleanPath = 'ODCI/' . $cleanPath;
        }
        
        return $documentRoot . '/' . $cleanPath;
    }

    /**
     * MIGRATION HELPER: Update old file paths to new structure
     * This can help migrate existing data from old paths
     */
    public static function migrateOldPaths($pdo)
    {
        try {
            // Find all media with old path structure
            $stmt = $pdo->prepare("
                SELECT id, file_path 
                FROM post_media 
                WHERE file_path LIKE 'uploads/posts/%' 
                OR file_path NOT LIKE 'social_feed/uploads/%'
            ");
            $stmt->execute();
            $oldMedia = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $updated = 0;
            $errors = [];
            
            foreach ($oldMedia as $media) {
                $oldPath = $media['file_path'];
                
                // Determine if it's an image or file based on extension
                $extension = strtolower(pathinfo($oldPath, PATHINFO_EXTENSION));
                $imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                $mediaType = in_array($extension, $imageExtensions) ? 'images' : 'files';
                
                // Extract filename from old path
                $filename = basename($oldPath);
                
                // Create new path
                $newPath = "social_feed/uploads/{$mediaType}/{$filename}";
                
                // Update database
                $updateStmt = $pdo->prepare("UPDATE post_media SET file_path = ? WHERE id = ?");
                if ($updateStmt->execute([$newPath, $media['id']])) {
                    $updated++;
                    error_log("Updated media path: {$oldPath} -> {$newPath}");
                } else {
                    $errors[] = "Failed to update media ID {$media['id']}";
                }
            }
            
            return [
                'updated' => $updated,
                'errors' => $errors,
                'success' => empty($errors)
            ];
            
        } catch (Exception $e) {
            error_log("Migration error: " . $e->getMessage());
            return [
                'updated' => 0,
                'errors' => [$e->getMessage()],
                'success' => false
            ];
        }
    }
}

?>