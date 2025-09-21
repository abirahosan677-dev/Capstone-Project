<?php
require_once 'config.php';

// Redirect to login if user is not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$username = $_SESSION['username'] ?? 'User';
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Support - Vibely</title>
<link rel="stylesheet" href="vibely.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
<style>
.auth-container {
    display: flex;
    justify-content: flex-start;
    align-items: flex-start;
    padding-top: 50px;
    min-height: 100vh;
    background-color: #f5f6fa;
}

.auth-form {
    width: 90%;
    max-width: 600px;
    margin: 0 auto;
    background: #fff;
    padding: 25px;
    border-radius: 10px;
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
    text-align: center;
}

.auth-form h1 {
    margin-bottom: 20px;
}


</style>
</head>
<body>
<div class="auth-container">
    <div class="auth-form">
        <h1>Support Unavailable</h1>
        <p>Sorry, the support feature is currently unavailable. Please try again later.</p>
        <a href="settings.php" class="button" style="color: white; background-color: #4a90e2; margin-bottom: 15px; display: inline-block;">
            <i class="fas fa-arrow-left"></i> Back to Settings</a>
    </div>
</div>
</body>
</html>