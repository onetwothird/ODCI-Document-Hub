<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/ODCI/includes/auth_check.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/ODCI/includes/config.php';

class MediaManager
{
    /**
     * Add media attachment to post
     */
    public static function addPostMedia($pdo, $postId, $mediaType, $filePath = null, $fileName = null, $originalName = null, $fileSize = null, $mimeType = null, $sortOrder = 0)
    {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO post_media (post_id, media_type, file_path, file_name, original_name, file_size, mime_type, sort_order) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");

            return $stmt->execute([
                $postId,
                $mediaType,
                $filePath,
                $fileName,
                $originalName,
                $fileSize,
                $mimeType,
                $sortOrder
            ]);
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

            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("Get post media error: " . $e->getMessage());
            return [];
        }
    }
}

?>