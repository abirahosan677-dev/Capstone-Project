<?php
require_once 'config.php';

if (isLoggedIn()) {
    header("Location: homepage.php");
    exit();
}

$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);
    $confirm_password = trim($_POST['confirm_password']);
    $role = trim($_POST['role']);

    if (empty($username) || empty($email) || empty($password) || empty($confirm_password) || empty($role)) {
        $error = "Please fill in all fields.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email address.";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match.";
    } elseif (!in_array($role, ['student', 'teacher', 'admin'])) {
        $error = "Invalid role selected.";
    } else {
        $conn = getDBConnection();

        // Ensure role column exists
        $result = $conn->query("SHOW COLUMNS FROM users LIKE 'role'");
        if ($result->num_rows == 0) {
            $conn->query("ALTER TABLE users ADD COLUMN role VARCHAR(20) DEFAULT 'student'");
        }

        // Check if username or email already exists
        $stmt = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $stmt->bind_param("ss", $username, $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $error = "Username or email already taken.";
        } else {
            // Insert new user with hashed password and role
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssss", $username, $email, $hashed_password, $role);
            if ($stmt->execute()) {
                header("Location: login.php?registered=1");
                exit();
            } else {
                $error = "Registration failed. Please try again.";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Sign Up - Vibely</title>
    <link rel="stylesheet" href="vibely.css" />
</head>
<body>
    <div class="auth-container">
        <div class="auth-form">
            <h1>Create Account</h1>
            <p>Sign up to start your Vibely journey</p>

            <?php if ($error): ?>
                <div class="error-message"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="POST" id="registerForm">
                <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>" />
                <input type="text" name="username" placeholder="Username" required />
                <input type="email" name="email" placeholder="Email" required />
                <input type="password" id="password" name="password" placeholder="Password" required />
                <input type="password" id="confirm_password" name="confirm_password" placeholder="Confirm Password" required />
                <select name="role" required style="width: 100%; padding: 10px; margin-bottom: 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box;">
                    <option value="">Select your role</option>
                    <option value="student">Student</option>
                    <option value="teacher">Teacher</option>
                    <option value="admin">Admin</option>
                </select>
                <label>
                    Show password
                    <input type="checkbox" id="showPassword" />
                </label>
                <button type="submit">Sign Up</button>
            </form>

            <p>Already have an account? <a href="login.php">Sign In</a></p>
        </div>
    </div>

    <script>
        document.getElementById("showPassword").addEventListener("change", function () {
            const passwordField = document.getElementById("password");
            const confirmPasswordField = document.getElementById("confirm_password");
            const type = this.checked ? "text" : "password";
            passwordField.type = type;
            confirmPasswordField.type = type;
        });
    </script>
</body>
</html>
