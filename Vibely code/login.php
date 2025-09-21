<?php
require_once 'config.php';

$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    if (empty($username) || empty($password)) {
        header("Location: login.php?error=wrongpassword");
        exit();
    }

    $conn = getDBConnection();

    $stmt = $conn->prepare("SELECT * FROM users WHERE username = ?");
    if (!$stmt) {
        die("Query error: " . $conn->error);
    }

    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();

        // Verify password
        if (password_verify($password, $user['password'])) {
            // Success → log in
            $_SESSION['user_id']   = $user['id'];
            $_SESSION['username']  = $user['username'];
            $_SESSION['user_token'] = bin2hex(random_bytes(32));

            header("Location: homepage.php");
            exit();
        }
    }

    header("Location: login.php?error=wrongpassword");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Vibely</title>
    <link rel="stylesheet" href="vibely.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <div class="auth-container">
        <div class="auth-form">
            <h1>Welcome Back</h1>
            <p>Sign in to continue your Vibely journey</p>
            
            <?php if (isset($_GET['error'])): ?>
                <div class="error-message">
                    <?php
                    $errors = [
                        'invalid_provider' => 'Invalid login provider',
                        'google_login_failed' => 'Google login failed. Please try again.',
                        'facebook_login_failed' => 'Facebook login failed. Please try again.',
                        'password_reset_sent' => 'Password reset instructions have been sent to your email.',
                        'password_reset_success' => 'Password reset successfully. You can now login with your new password.'
                    ];
                    echo $errors[$_GET['error']] ?? 'Wrong username or password.';
                    ?>
                </div>
            <?php endif; ?>
            
            <form id="loginForm" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                <input type="text" name="username" placeholder="Username" required>
                        <input type="password" id="password" name="password" placeholder="Password" required>
                        <label>
                            Show password
                            <input type="checkbox" id="showPassword">
                        </label>
                <button type="submit">Sign In</button>
            </form>

            <script>
            document.getElementById("showPassword").addEventListener("change", function() {
                const passwordField = document.getElementById("password");
                passwordField.type = this.checked ? "text" : "password";
            });
            </script>
            
            <div class="auth-links">
                <p>Don't have an account? <a href="register.php">Sign Up</a></p>
                <p><a href="password_recovery.php">Forgot your password?</a></p>
            </div>
        </div>
    </div>
    
</body>
</html>
