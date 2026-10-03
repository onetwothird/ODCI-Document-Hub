<?php
// Include the connection file (which defines $pdo)
include '../includes/config.php';  // Adjust the path if needed

$message = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $new_password = $_POST['new_password'];

    if (!empty($username) && !empty($new_password)) {
        // Hash the new password
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

        try {
            // Prepare SQL with PDO
            $stmt = $pdo->prepare("UPDATE users SET password = ?, updated_at = NOW() WHERE username = ?");
            $stmt->execute([$hashed_password, $username]);

            if ($stmt->rowCount() > 0) {
                $message = "Password successfully updated for user: <strong>$username</strong>";
            } else {
                $message = "No user found with username: <strong>$username</strong>";
            }
        } catch (PDOException $e) {
            $message = "Error updating password: " . $e->getMessage();
        }
    } else {
        $message = "Please fill in both fields.";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Reset Password</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f2f2f2;
            padding: 50px;
        }
        .container {
            background: #fff;
            padding: 20px;
            max-width: 400px;
            margin: auto;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }
        input[type="text"], input[type="password"] {
            width: 100%;
            padding: 10px;
            margin: 10px 0;
        }
        button {
            padding: 10px 20px;
            background: #28a745;
            color: white;
            border: none;
            cursor: pointer;
        }
        .message {
            margin-top: 10px;
            padding: 10px;
            background: #e9ecef;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>Reset User Password</h2>
        <form method="POST" action="">
            <label>Username:</label>
            <input type="text" name="username" required>
            <label>New Password:</label>
            <input type="password" name="new_password" required>
            <button type="submit">Reset Password</button>
        </form>
        <?php if ($message): ?>
            <div class="message"><?php echo $message; ?></div>
        <?php endif; ?>
    </div>
</body>
</html>
