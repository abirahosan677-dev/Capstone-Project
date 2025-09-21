<?php
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$conn = getDBConnection();
$user_id = $_SESSION['user_id'];

// Check user role
$stmt = $conn->prepare("SELECT LOWER(role) FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($role);
$stmt->fetch();
$stmt->close();

if ($role !== 'teacher' && $role !== 'admin') {
    die("Access denied. Only teachers and admins can view submissions.");
}

// Get assignment ID from query
$assignment_id = intval($_GET['assignment_id'] ?? 0);
if ($assignment_id <= 0) die("Invalid assignment ID.");

// Fetch assignment info
$stmt = $conn->prepare("SELECT * FROM assignments WHERE id=?");
$stmt->bind_param("i", $assignment_id);
$stmt->execute();
$assignment_res = $stmt->get_result();
$assignment = $assignment_res->fetch_assoc();
$stmt->close();
if (!$assignment) die("Assignment not found.");

// Fetch submissions
$stmt = $conn->prepare("
    SELECT s.*, u.username AS student_name 
    FROM assignment_submissions s
    LEFT JOIN users u ON s.student_id = u.id
    WHERE s.assignment_id = ?
    ORDER BY submitted_at DESC
");
$stmt->bind_param("i", $assignment_id);
$stmt->execute();
$res = $stmt->get_result();
$submissions = [];
while ($row = $res->fetch_assoc()) {
    $submissions[] = $row;
}
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Submissions - <?= htmlspecialchars($assignment['title']) ?></title>
<link rel="stylesheet" href="vibely.css">
<style>
body { font-family: Arial, sans-serif; margin:0; background:#f5f6fa; }
.container { width:90%; max-width:800px; margin:50px auto; }
.submission { background:#fff; padding:15px; margin-bottom:10px; border-radius:8px; box-shadow:0 2px 4px rgba(0,0,0,0.1);}
.submission .info { font-size:0.85rem; color:#555; margin-bottom:5px; }
.back-btn { display:inline-block; margin-top:10px; color:#4a90e2; text-decoration:none; }
</style>
</head>
<body>
<div class="container">
    <h1>Submissions for: <?= htmlspecialchars($assignment['title']) ?></h1>
    <?php if (empty($submissions)): ?>
        <p>No submissions yet.</p>
    <?php else: ?>
        <?php foreach ($submissions as $s): ?>
            <div class="submission">
                <div class="info">
                    <?= htmlspecialchars($s['student_name']) ?> - <?= $s['submitted_at'] ?>
                    <?php if ($role === 'teacher' || $role === 'admin'): ?>
                        | <a href="grade_submission.php?id=<?= $s['id'] ?>">📝 Grade & Feedback</a>
                    <?php endif; ?>
                    <?php if ($role === 'admin'): ?>
                        | <a href="edit_submission.php?id=<?= $s['id'] ?>">✏️ Edit</a>
                        | <a href="delete_submission.php?id=<?= $s['id'] ?>" onclick="return confirm('Are you sure you want to delete this submission?')">🗑️ Delete</a>
                    <?php endif; ?>
                </div>
                <div class="content"><?= nl2br(htmlspecialchars($s['content'])) ?></div>

                <?php if (!empty($s['grade']) || !empty($s['feedback'])): ?>
                    <div style="margin-top:10px; padding:10px; background:#f0f8ff; border-radius:5px; border-left:3px solid #4a90e2;">
                        <?php if (!empty($s['grade'])): ?>
                            <div style="font-weight:bold; color:#2e7d32;">📈 Grade: <?= htmlspecialchars($s['grade']) ?></div>
                        <?php endif; ?>
                        <?php if (!empty($s['feedback'])): ?>
                            <div style="margin-top:5px; color:#555;">
                                <strong>💬 Feedback:</strong><br>
                                <?= nl2br(htmlspecialchars($s['feedback'])) ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($s['image_path']) && $s['image_path'] !== null && file_exists($s['image_path'])): ?>
                    <?php $file_ext = strtolower(pathinfo($s['image_path'], PATHINFO_EXTENSION)); ?>
                    <?php if ($file_ext === 'mp4'): ?>
                        <video controls style="max-width: 100%; height: 200px; object-fit: cover; border-radius: 5px; margin: 10px 0;">
                            <source src="<?= htmlspecialchars($s['image_path']) ?>" type="video/mp4">
                            Your browser does not support the video tag.
                        </video>
                    <?php else: ?>
                        <img src="<?= htmlspecialchars($s['image_path']) ?>" alt="Submission Image" style="max-width: 100%; height: 200px; object-fit: cover; border-radius: 5px; margin: 10px 0;">
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
    <a href="assignment.php" class="back-btn">Back to Assignments</a>
</div>
</body>
</html>
