<?php
require_once 'config.php';

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$conn = getDBConnection();
$username = $_SESSION['username'] ?? 'User'; // reporter_name

// Ensure post_id is passed via GET for displaying the post
if (!isset($_GET['post_id'])) {
    header("Location: content.php");
    exit();
}

$post_id = intval($_GET['post_id']);

// Fetch the post from posts datastore
$stmt = $conn->prepare("SELECT * FROM posts WHERE Post_ID = ?");
if (!$stmt) {
    die("Prepare failed: " . $conn->error);
}
$stmt->bind_param("i", $post_id);
$stmt->execute();
$result = $stmt->get_result();
$post = $result->fetch_assoc();
$stmt->close();

if (!$post) {
    die("Post not found.");
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reason'])) {
    $reason = trim($_POST['reason']);

    if ($reason !== '') {
        $stmt = $conn->prepare("INSERT INTO reports (post_id, reporter_name, reason, created_at) VALUES (?, ?, ?, NOW())");
        if (!$stmt) {
            die("Prepare failed: " . $conn->error);
        }
        $stmt->bind_param("iss", $post_id, $username, $reason);
        $stmt->execute();
        $stmt->close();

        // Redirect to success page
        header("Location: reportsuccess.php");
        exit();
    } else {
        $error = "Please provide a reason for reporting.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Report Post - Vibely</title>
<link rel="stylesheet" href="vibely.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
<style>
body { font-family: Arial, sans-serif; margin:0; background:#f5f6fa; }
.container { width:90%; max-width:600px; margin:50px auto; background:#fff; padding:20px; border-radius:8px; box-shadow:0 2px 6px rgba(0,0,0,0.1); }
.post { padding:10px; border:1px solid #ccc; border-radius:5px; margin-bottom:20px; background:#fafafa; }
textarea { width:100%; padding:10px; border-radius:5px; border:1px solid #ccc; margin-bottom:10px; }
button { padding:10px 15px; border:none; border-radius:5px; background:#e91e63; color:white; cursor:pointer; }
.back-btn { display:inline-block; margin-top:10px; color:#4a90e2; text-decoration:none; }
.error { color:red; margin-bottom:10px; }
</style>
</head>
<body>
<div class="container">
    <h2>Report Post</h2>

    <div class="post">
        <strong><?= htmlspecialchars($post['Username']) ?></strong> - <?= $post['Publish_Date'] ?><br>
        <?= htmlspecialchars($post['Content']) ?>
        <?php if ($post['URL']) echo "<br><a href='".htmlspecialchars($post['URL'])."' target='_blank'>Link</a>"; ?>
    </div>

    <?php if (!empty($error)) echo "<div class='error'>".htmlspecialchars($error)."</div>"; ?>

    <form method="POST">
        <textarea name="reason" placeholder="Reason for reporting..." required></textarea>
        <button type="submit"><i class="fas fa-flag"></i> Submit Report</button>
    </form>

    <a href="content.php" class="back-btn"><i class="fas fa-arrow-left"></i> Back to Posts</a>
</div>
</body>
</html>
