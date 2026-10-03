<?php
// test_images.php - Quick test to verify image paths
require_once 'includes/config.php';

echo "<!DOCTYPE html><html><head><title>Image Test</title></head><body>";
echo "<h1>Quick Image Path Test</h1>";

// Test the users with profile images from your database
$testUsers = [
    ['id' => 1, 'profile_image' => 'uploads/profile_images/profile_1_1756380279.jpg'],
    ['id' => 28, 'profile_image' => 'uploads/profile_images/profile_28_1756533176.jpg'],
    ['id' => 30, 'profile_image' => 'uploads/profile_images/profile_30_1756577645.jpg']
];

foreach ($testUsers as $user) {
    $url = getProfileImageUrl($user['profile_image']);
    $serverPath = $_SERVER['DOCUMENT_ROOT'] . '/' . $url;
    
    echo "<div style='border: 1px solid #ddd; padding: 10px; margin: 10px;'>";
    echo "<h3>User ID: {$user['id']}</h3>";
    echo "<p><strong>Database Path:</strong> {$user['profile_image']}</p>";
    echo "<p><strong>Generated URL:</strong> $url</p>";
    echo "<p><strong>Server Path:</strong> $serverPath</p>";
    echo "<p><strong>File Exists:</strong> " . (file_exists($serverPath) ? "Yes" : "No") . "</p>";
    
    echo "<p><strong>Image Test:</strong></p>";
    echo "<img src='$url' style='width: 100px; height: 100px; object-fit: cover; border: 1px solid #ccc;' 
          onerror='this.style.border=\"2px solid red\"; this.alt=\"FAILED: \" + this.src;'>";
    echo "</div>";
}

echo "</body></html>";
?>