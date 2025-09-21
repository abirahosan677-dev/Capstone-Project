<?php
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$conn = getDBConnection();
$user_id = $_SESSION['user_id'];

// Check user role - only teachers and admins can grade submissions
$stmt = $conn->prepare("SELECT LOWER(role) FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($role);
$stmt->fetch();
$stmt->close();

if ($role !== 'teacher' && $role !== 'admin') {
    die("Access denied. Only teachers and admins can grade submissions.");
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
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['grade_submission'])) {
        $grade = trim($_POST['grade'] ?? '');
        $feedback = trim($_POST['feedback'] ?? '');

        $stmt = $conn->prepare("UPDATE assignment_submissions SET grade=?, feedback=? WHERE id=?");
        $stmt->bind_param("ssi", $grade, $feedback, $submission_id);
        $stmt->execute();
        $stmt->close();

        header("Location: view_submissions.php?assignment_id=" . $submission['assignment_id']);
        exit();
    }

    // Handle delete grade/feedback for admins
    if (isset($_POST['delete_grade_feedback']) && $role === 'admin') {
        $stmt = $conn->prepare("UPDATE assignment_submissions SET grade=NULL, feedback=NULL WHERE id=?");
        $stmt->bind_param("i", $submission_id);
        $stmt->execute();
        $stmt->close();

        header("Location: view_submissions.php?assignment_id=" . $submission['assignment_id']);
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Grade Submission - Vibely</title>
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
.submission-preview { background:#f9f9f9; padding:15px; border-radius:5px; margin-bottom:20px; border-left:4px solid #4a90e2; }
</style>
</head>
<body>
<div class="container">
    <h1>📝 Grade & Provide Feedback</h1>
    <p><strong>Assignment:</strong> <?= htmlspecialchars($submission['assignment_title']) ?></p>
    <p><strong>Student:</strong> <?= htmlspecialchars($submission['student_name']) ?></p>
    <p><strong>Submitted:</strong> <?= $submission['submitted_at'] ?></p>

    <!-- Submission Preview -->
    <div class="submission-preview">
        <h3>Student's Submission:</h3>
        <div style="margin-bottom:10px;"><strong>Content:</strong></div>
        <div style="padding:10px; background:white; border-radius:3px; margin-bottom:15px;">
            <?= nl2br(htmlspecialchars($submission['content'])) ?>
        </div>

        <?php if (!empty($submission['image_path']) && $submission['image_path'] !== null && file_exists($submission['image_path'])): ?>
            <div style="margin-bottom:10px;"><strong>Attached File:</strong></div>
            <?php $file_ext = strtolower(pathinfo($submission['image_path'], PATHINFO_EXTENSION)); ?>
            <?php if ($file_ext === 'mp4'): ?>
                <video controls style="max-width: 100%; height: 200px; object-fit: cover; border-radius: 5px; margin: 10px 0;">
                    <source src="<?= htmlspecialchars($submission['image_path']) ?>" type="video/mp4">
                    Your browser does not support the video tag.
                </video>
            <?php else: ?>
                <img src="<?= htmlspecialchars($submission['image_path']) ?>" alt="Submission Image" style="max-width: 100%; height: 200px; object-fit: cover; border-radius: 5px; margin: 10px 0;">
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <!-- Existing Grade & Feedback Section -->
    <?php if (!empty($submission['grade']) || !empty($submission['feedback'])): ?>
    <div style="background:#e8f5e8; padding:15px; border-radius:5px; margin-bottom:20px; border-left:4px solid #4caf50;">
        <h3 style="margin-top:0; color:#2e7d32;">📊 Existing Grade & Feedback</h3>
        <?php if (!empty($submission['grade'])): ?>
            <div style="margin-bottom:10px;">
                <strong>Grade:</strong> <span style="background:#4caf50; color:white; padding:2px 8px; border-radius:3px;"><?= htmlspecialchars($submission['grade']) ?></span>
            </div>
        <?php endif; ?>
        <?php if (!empty($submission['feedback'])): ?>
            <div style="margin-bottom:10px;">
                <strong>Feedback:</strong>
                <div style="padding:10px; background:white; border-radius:3px; margin-top:5px; border:1px solid #ddd;">
                    <?= nl2br(htmlspecialchars($submission['feedback'])) ?>
                </div>
            </div>
        <?php endif; ?>
        <?php if ($role === 'admin'): ?>
            <div style="margin-top:15px; padding-top:10px; border-top:1px solid #ddd;">
                <strong>Admin Actions:</strong>
                <form method="POST" style="display:inline; margin-left:10px;">
                    <input type="hidden" name="delete_grade_feedback" value="1">
                    <button type="submit" onclick="return confirm('Are you sure you want to delete this grade and feedback?')" style="background:#f44336; padding:5px 10px; font-size:0.9em;">🗑️ Delete Grade & Feedback</button>
                </form>
            </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <form method="POST">
        <input type="hidden" name="grade_submission" value="1">

        <label for="grade"><strong>Grade (optional):</strong></label>
        <input type="text" name="grade" value="<?= htmlspecialchars($submission['grade'] ?? '') ?>" placeholder="Enter grade (e.g., 85/100, A-, Pass)">

        <label for="feedback"><strong>Feedback (optional):</strong></label>
        <textarea name="feedback" rows="5" placeholder="Provide constructive feedback for the student..."><?= htmlspecialchars($submission['feedback'] ?? '') ?></textarea>

        <button type="submit">💾 Save Grade & Feedback</button>
        <a href="view_submissions.php?assignment_id=<?= $submission['assignment_id'] ?>" class="back-btn">❌ Cancel</a>
    </form>

    <a href="view_submissions.php?assignment_id=<?= $submission['assignment_id'] ?>" class="back-btn">⬅️ Back to Submissions</a>
</div>
</body>
</html>
