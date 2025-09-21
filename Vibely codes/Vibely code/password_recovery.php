<?php
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitize($_POST['email']);
    $errors = [];

    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Please enter a valid email address.";
    }

    if (empty($errors)) {
        $conn = getDBConnection();
        $stmt = $conn->prepare("SELECT id, username FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            $reset_token = generateToken();
            $reset_token_expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

            $stmt = $conn->prepare("UPDATE users SET reset_token = ?, reset_token_expires = ? WHERE id = ?");
            $stmt->bind_param("ssi", $reset_token, $reset_token_expires, $user['id']);
            
            if ($stmt->execute()) {
                $reset_link = APP_URL . "/reset_password.php?token=" . $reset_token;
                $subject = "Password Reset Request - Vibely";
                $message = "
                    <h2>Password Reset Request</h2>
                    <p>Hello " . htmlspecialchars($user['username']) . ",</p>
                    <p>You requested to reset your password. Click the link below to reset your password:</p>
                    <p><a href='$reset_link'>Reset Password</a></p>
                    <p>This link will expire in 1 hour.</p>
                    <p>If you didn't request this, please ignore this email.</p>
                ";

                if (sendEmail($email, $subject, $message)) {
                    header("Location: login.php?error=password_reset_sent");
                    exit();
                } else {
                    $errors[] = "Failed to send reset email. Please try again.";
                }
            } else {
                $errors[] = "Failed to generate reset token. Please try again.";
            }
        } else {
            $errors[] = "No account found with that email address.";
        }
    }
}

// Handle password reset
if (isset($_GET['token'])) {
    $token = sanitize($_GET['token']);
    $conn = getDBConnection();
    $stmt = $conn->prepare("SELECT id FROM users WHERE reset_token = ? AND reset_token_expires > NOW()");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['new_password'])) {
            $new_password = $_POST['new_password'];
            $confirm_password = $_POST['confirm_password'];
            
            if (strlen($new_password) < 6) {
                $errors[] = "Password must be at least 6 characters.";
            } elseif ($new_password !== $confirm_password) {
                $errors[] = "Passwords do not match.";
            } else {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("UPDATE users SET password = ?, reset_token = NULL, reset_token_expires = NULL WHERE id = ?");
                $stmt->bind_param("si", $hashed_password, $user['id']);
                
                if ($stmt->execute()) {
                    header("Location: login.php?error=password_reset_success");
                    exit();
                } else {
                    $errors[] = "Failed to reset password. Please try again.";
                }
            }
        }
        
        // Show reset form
        showResetForm($token, $errors);
        exit();
    } else {
        $errors[] = "Invalid or expired reset token.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Password Recovery - Vibely</title>
    <link rel="stylesheet" href="vibely.css">
</head>
<body>
    <div class="auth-container">
        <div class="auth-form">
            <h1>Password Recovery</h1>
            
            <?php if (!empty($errors)): ?>
                <div class="error-message">
                    <?php foreach ($errors as $error): ?>
                        <p><?php echo $error; ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <form method="POST">
                <p>Enter your email address to receive a password reset link:</p>
                <input type="email" name="email" placeholder="Email Address" required>
                <button type="submit">Send Reset Link</button>
            </form>
            
            <div class="auth-links">
                <p><a href="login.php">Back to Login</a></p>
            </div>
        </div>
    </div>

    <script src="js/vibely.js"></script>
</body>
</html>

<?php
function showResetForm($token, $errors = []) {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Reset Password - Vibely</title>
        <link rel="stylesheet" href="vibely.css">
    </head>
    <body>
        <div class="auth-container">
            <div class="auth-form">
                <h1>Reset Password</h1>
                
                <?php if (!empty($errors)): ?>
                    <div class="error-message">
                        <?php foreach ($errors as $error): ?>
                            <p><?php echo $error; ?></p>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <form method="POST">
                    <input type="hidden" name="token" value="<?php echo $token; ?>">
                    <input type="password" name="new_password" placeholder="New Password" required>
                    <input type="password" name="confirm_password" placeholder="Confirm Password" required>
                    <button type="submit">Reset Password</button>
                </form>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit();
}
?>
