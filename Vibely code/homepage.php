<?php
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$conn = getDBConnection();
$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'] ?? 'User';

// Fetch user role
$stmt = $conn->prepare("SELECT role, theme FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$role = $user['role'] ?? 'Student';
$theme = $user['theme'] ?? 'light';


?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Homepage - Vibely</title>
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
    max-width: 900px;
    margin: 0 auto;
}

.dashboard-cards, .dashboard-section {
    display: flex;
    flex-wrap: wrap;
    gap: 15px;
    margin: 20px 0;
}

.card {
    background: #fff;
    padding: 20px;
    border-radius: 10px;
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
    text-align: center;
    flex: 1 1 200px;
}

.card i {
    font-size: 2rem;
    margin-bottom: 10px;
    color: #4a90e2;
}

.card .button {
    margin-top: 10px;
    display: inline-block;
    padding: 10px 15px;
    background-color: #4a90e2;
    color: white;
    border-radius: 5px;
    text-decoration: none;
}

.logout-btn {
    background-color: #e74c3c;
}

.homepage-content {
    margin-top: 30px;
    text-align: left;
}

.homepage-content ul {
    list-style-type: disc;
    padding-left: 20px;
}

/* Dashboard Section Cards */
.dashboard-section .card i {
    font-size: 1.8rem;
    color: #27ae60;
}

.dashboard-section .card h3 {
    font-size: 1.1rem;
    margin: 5px 0;
}

.dashboard-section .card p {
    font-size: 0.95rem;
}
</style>
</head>
<body class="<?php echo $theme; ?>">
<div class="auth-container">
    <div class="auth-form">
        <h1>Hello, <?php echo htmlspecialchars($username); ?>!</h1>
        <p>Welcome back to Vibely. Here's your dashboard:</p>

        <!-- Top Row: Main Dashboard Cards -->
        <div class="dashboard-cards">
            <div class="card">
                <i class="fas fa-user"></i>
                <h3>Profile</h3>
                <p>View and edit your profile information.</p>
                <a href="profile.php" class="button">Go to Profile</a>
            </div>
            <div class="card">
                <i class="fas fa-cog"></i>
                <h3>Settings</h3>
                <p>Manage your account settings and preferences.</p>
                <a href="settings.php" class="button">Go to Settings</a>
            </div>
            <div class="card">
                <i class="fas fa-users"></i>
                <h3>Community Posts</h3>
                <p>Share and interact with community posts.</p>
                <a href="community.php" class="button">View Posts</a>
            </div>
        </div>

        <!-- Bottom Row: Functional Features -->
        <div class="dashboard-section">
            <div class="card">
                <i class="fas fa-envelope"></i>
                <h3>Messaging</h3>
                <p>Check and send messages to other users.</p>
                <a href="messages.php" class="button">Open Messages</a>
            </div>
            <div class="card">
                <i class="fas fa-book-open"></i>
                <h3>Content Viewing</h3>
                <p>Browse courses, materials, or shared content.</p>
                <a href="content.php" class="button">View Content</a>
            </div>
            <div class="card">
                <i class="fas fa-tasks"></i>
                <h3>Assignments</h3>
                <p>View, submit, and track your assignments.</p>
                <a href="assignment.php" class="button">Go to Assignments</a>
            </div>
        </div>

    </div>
</div>


</body>
</html>
