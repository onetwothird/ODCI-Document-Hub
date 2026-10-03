<?php

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/config.php';

class UtilityManager
{
    /**
     * Format time ago
     */
    public static function timeAgo($datetime)
    {
        $time = time() - strtotime($datetime);

        if ($time < 60)
            return 'just now';
        if ($time < 3600)
            return floor($time / 60) . ' minutes ago';
        if ($time < 86400)
            return floor($time / 3600) . ' hours ago';
        if ($time < 2592000)
            return floor($time / 86400) . ' days ago';
        if ($time < 31104000)
            return floor($time / 2592000) . ' months ago';

        return floor($time / 31104000) . ' years ago';
    }
}

?>