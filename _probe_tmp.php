<?php
require_once __DIR__ . '/includes/config.php';
header('Content-Type: text/plain');
echo "REQUEST_METHOD : " . $_SERVER['REQUEST_METHOD'] . "\n";
echo "CONTENT_TYPE   : " . ($_SERVER['CONTENT_TYPE'] ?? '(none)') . "\n";
echo "CONTENT_LENGTH : " . ($_SERVER['CONTENT_LENGTH'] ?? '(none)') . "\n";
$raw = file_get_contents('php://input');
echo "php://input len: " . strlen((string)$raw) . "\n";
echo "php://input    : [" . substr((string)$raw, 0, 200) . "]\n";
echo "_POST count    : " . count($_POST) . "\n";
echo "enable_post_data_reading: " . ini_get('enable_post_data_reading') . "\n";
