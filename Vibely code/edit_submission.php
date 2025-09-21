<?php
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$conn = getDBConnection();
$user_id = $_SESSION['user_id'];

// Check user role - only admins can edit submissions
$stmt = $conn->prepare("SELECT LOWER(role) FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($role);
$stmt->fetch();
$stmt->close();

if ($role !== 'admin') {
    die("Access denied. Only admins can edit submissions.");
}

// Get submission ID from query
$submission_id = intval($_GET['id'] ?? 0);
if ($submission_id <= 0) die("Invalid submission ID.");

// Fetch submission details
$stmt = $conn->prepare("
    SELECT s.*, a.title AS assignment_title, u.username AS student_name
    FROM assignment_submissions s
    LEFT JOIN assignments a ON s.assignment_id = a.id
    LEFT JOIN users u ON s.student_id = u.id
    WHERE s.id = ?
");
$stmt->bind_param("i", $submission_id);
$stmt->execute();
$result = $stmt->get_result();
$submission = $result->fetch_assoc();
$stmt->close();

if (!$submission) die("Submission not found.");

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_submission'])) {
    $content = trim($_POST['content']);

    if ($content !== '') {
        $stmt = $conn->prepare("UPDATE assignment_submissions SET content=? WHERE id=?");
        $stmt->bind_param("si", $content, $submission_id);
        $stmt->execute();
        $stmt->close();

        header("Location: view_submissions.php?assignment_id=" . $submission['assignment_id']);
        exit();
    } else {
        $error = "Content cannot be empty.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Edit Submission - Vibely</title>
<link rel="stylesheet" href="vibely.css">
<style>
body { font-family: Arial, sans-serif; margin:0; background:#f5f6fa; }
.container { width:90%; max-width:800px; margin:50px auto; }
form { background:#fff; padding:20px; border-radius:8px; box-shadow:0 2px 6px rgba(0,0,0,0.1); }
input[type=text], input[type=number], textarea { width:100%; padding:10px; margin-bottom:10px; border-radius:5px; border:1px solid #ccc; }
button { padding:10px 15px; border:none; border-radius:5px; background:#4a90e2; color:white; cursor:pointer; margin-right:5px; }
button:hover { background:#357ABD; }
.error { color:#e91e63; margin-bottom:10px; background:#ffebee; padding:10px; border-radius:5px; }
.back-btn { display:inline-block; margin-top:20px; color:#4a90e2; text-decoration:none; padding:10px 20px; border:1px solid #4a90e2; border-radius:5px; }
.back-btn:hover { background:#4a90e2; color:white; }
</style>
</head>
<body>
<div class="container">
    <h1>Edit Submission</h1>
    <p><strong>Assignment:</strong> <?= htmlspecialchars($submission['assignment_title']) ?></p>
    <p><strong>Student:</strong> <?= htmlspecialchars($submission['student_name']) ?></p>
    <p><strong>Submitted:</strong> <?= $submission['submitted_at'] ?></p>

    <?php if (!empty($error)): ?>
        <div class="error">⚠️ <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST">
        <input type="hidden" name="edit_submission" value="1">

        <label for="content"><strong>Submission Content:</strong></label>
        <textarea name="content" rows="6" required><?= htmlspecialchars($submission['content']) ?></textarea>

        <div style="background:#fff3cd; border:1px solid #ffeaa7; border-radius:5px; padding:10px; margin-bottom:15px;">
            <strong>⚠️ Note:</strong> This page is for editing submission content only. Use the "Grade & Feedback" link to add grades and feedback.
        </div>

        <button type="submit">💾 Save Changes</button>
        <a href="view_submissions.php?assignment_id=<?= $submission['assignment_id'] ?>" class="back-btn">❌ Cancel</a>
    </form>

    <a href="view_submissions.php?assignment_id=<?= $submission['assignment_id'] ?>" class="back-btn">⬅️ Back to Submissions</a>
</div>
</body>
</html>
