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
$stmt = $conn->prepare("SELECT role FROM users WHERE id=?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($role);
$stmt->fetch();
$stmt->close();

// Only students can access this page
if (strtolower($role) !== 'student') {
    header("Location: assignment.php");
    exit();
}

// Get assignment ID from URL
$assignment_id = isset($_GET['assignment_id']) ? (int)$_GET['assignment_id'] : 0;

if ($assignment_id === 0) {
    header("Location: assignment.php");
    exit();
}

// Fetch assignment details
$stmt = $conn->prepare("
    SELECT a.*, u.username AS creator_name
    FROM assignments a
    LEFT JOIN users u ON a.created_by = u.id
    WHERE a.id = ?
");
$stmt->bind_param("i", $assignment_id);
$stmt->execute();
$result = $stmt->get_result();
$assignment = $result->fetch_assoc();
$stmt->close();

if (!$assignment) {
    header("Location: assignment.php");
    exit();
}

// Fetch student's existing submission if any
$stmt = $conn->prepare("
    SELECT * FROM assignment_submissions
    WHERE assignment_id = ? AND student_id = ?
");
$stmt->bind_param("ii", $assignment_id, $user_id);
$stmt->execute();
$existing_submission = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Create uploads directory if it doesn't exist
$upload_dir = 'uploads/assignments/';
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// Handle submission (only allow if no existing submission)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_assignment']) && !$existing_submission) {
    $content = trim($_POST['submission']);
    $image_path = null;

    // Handle image upload for submission
    if (isset($_FILES['submission_image']) && $_FILES['submission_image']['error'] === UPLOAD_ERR_OK) {
        $file_name = $_FILES['submission_image']['name'];
        $file_tmp = $_FILES['submission_image']['tmp_name'];
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

        // Validate file type
        $allowed_exts = ['jpg', 'jpeg', 'png', 'gif', 'mp4'];
        if (in_array($file_ext, $allowed_exts)) {
            $new_file_name = $user_id . '_submission_' . time() . '_' . uniqid() . '.' . $file_ext;
            $upload_path = $upload_dir . $new_file_name;

            if (move_uploaded_file($file_tmp, $upload_path)) {
                $image_path = $upload_path;
            }
        }
    }

    if ($content !== '') {
        // Create new submission
        $stmt = $conn->prepare("INSERT INTO assignment_submissions (assignment_id, student_id, content, image_path) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("iiss", $assignment_id, $user_id, $content, $image_path);
        $stmt->execute();
        $stmt->close();
        $success = "Your assignment has been submitted successfully!";

        // Refresh the page to show updated data
        header("Location: submit_assignment.php?assignment_id=" . $assignment_id . "&success=1");
        exit();
    } else {
        $error = "Please provide content for your submission.";
    }
}

// Check for success message
$success = isset($_GET['success']) ? "Your assignment has been submitted successfully!" : "";
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Submit Assignment - Vibely</title>
<link rel="stylesheet" href="vibely.css">
<style>
body { font-family: Arial, sans-serif; margin:0; background:#f5f6fa; }
.container { width:90%; max-width:900px; margin:50px auto; }
.assignment-details { background:#fff; padding:20px; border-radius:8px; box-shadow:0 2px 6px rgba(0,0,0,0.1); margin-bottom:20px; }
.submission-form { background:#fff; padding:20px; border-radius:8px; box-shadow:0 2px 6px rgba(0,0,0,0.1); }
input[type=text], textarea { width:100%; padding:10px; margin-bottom:10px; border-radius:5px; border:1px solid #ccc; }
input[type=file] { margin-bottom:10px; }
button { padding:10px 15px; border:none; border-radius:5px; background:#4a90e2; color:white; cursor:pointer; margin-right:5px; }
button:hover { background:#357ABD; }
.assignment-image { max-width:100%; height:200px; object-fit:cover; border-radius:5px; margin:10px 0; }
.error { color:#e91e63; margin-bottom:10px; background:#ffebee; padding:10px; border-radius:5px; }
.success { color:#4caf50; margin-bottom:10px; background:#e8f5e8; padding:10px; border-radius:5px; }
.back-btn { display:inline-block; margin-top:20px; color:#4a90e2; text-decoration:none; padding:10px 20px; border:1px solid #4a90e2; border-radius:5px; }
.back-btn:hover { background:#4a90e2; color:white; }
.meta { display:flex; justify-content:space-between; align-items:center; margin-bottom:10px; }
.subject { background:#e8f4fd; color:#4a90e2; padding:3px 8px; border-radius:12px; font-size:0.8rem; }
.due-date { color:#e91e63; font-weight:bold; }
</style>
</head>
<body>
<div class="container">
    <h1>📤 Submit Assignment</h1>

    <?php if (!empty($error)): ?>
        <div class="error">⚠️ <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if (!empty($success)): ?>
        <div class="success">✅ <?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <!-- Assignment Details -->
    <div class="assignment-details">
        <div class="meta">
            <div style="font-size:0.85rem; color:#555;">
                👤 <?= htmlspecialchars($assignment['creator_name'] ?? 'Unknown') ?> • 📅 <?= date('M j, Y', strtotime($assignment['created_at'])) ?>
            </div>
            <?php if (!empty($assignment['subject'])): ?>
                <div class="subject">📚 <?= htmlspecialchars($assignment['subject']) ?></div>
            <?php endif; ?>
        </div>

        <h2>📖 <?= htmlspecialchars($assignment['title']) ?></h2>
        <div style="margin-bottom:15px;"><?= nl2br(htmlspecialchars($assignment['description'])) ?></div>

        <?php if (!empty($assignment['image_path']) && file_exists($assignment['image_path'])): ?>
            <?php $file_ext = strtolower(pathinfo($assignment['image_path'], PATHINFO_EXTENSION)); ?>
            <?php if ($file_ext === 'mp4'): ?>
                <video controls style="max-width: 100%; height: 200px; object-fit: cover; border-radius: 5px; margin: 10px 0;">
                    <source src="<?= htmlspecialchars($assignment['image_path']) ?>" type="video/mp4">
                    Your browser does not support the video tag.
                </video>
            <?php else: ?>
                <img src="<?= htmlspecialchars($assignment['image_path']) ?>" alt="Assignment Image" class="assignment-image">
            <?php endif; ?>
        <?php endif; ?>

        <?php if (!empty($assignment['due_date'])): ?>
            <div class="due-date">⏰ Due: <?= date('M j, Y', strtotime($assignment['due_date'])) ?></div>
        <?php endif; ?>

        <?php if (!empty($assignment['max_points'])): ?>
            <div style="color:#666; font-size:0.9rem;">📊 Points: <?= $assignment['max_points'] ?></div>
        <?php endif; ?>
    </div>

    <!-- Submission Section -->
    <div class="submission-form">
        <?php if ($existing_submission): ?>
            <!-- Already submitted - show message -->
            <h3>✅ Submission Complete</h3>
            <div style="padding:20px; background:#e8f5e8; border:1px solid #4caf50; border-radius:5px; margin-bottom:15px;">
                <h4 style="color:#2e7d32; margin-top:0;">📝 You have already submitted this assignment</h4>
                <p style="margin-bottom:0; color:#2e7d32;">Your submission was received and cannot be modified. Please contact your teacher if you need to make changes.</p>
            </div>

            <!-- Show submitted content -->
            <div style="margin-bottom:15px;">
                <h4>Your Submitted Content:</h4>
                <div style="padding:15px; background:#f9f9f9; border-radius:5px; border-left:4px solid #4a90e2;">
                    <?= nl2br(htmlspecialchars($existing_submission['content'])) ?>
                </div>
            </div>

            <?php if (!empty($existing_submission['image_path']) && file_exists($existing_submission['image_path'])): ?>
                <div style="margin-bottom:15px;">
                    <h4>Submitted File:</h4>
                    <?php $file_ext = strtolower(pathinfo($existing_submission['image_path'], PATHINFO_EXTENSION)); ?>
                    <?php if ($file_ext === 'mp4'): ?>
                        <video controls style="max-width: 100%; height: 200px; object-fit: cover; border-radius: 5px; margin: 10px 0; border:2px solid #4a90e2;">
                            <source src="<?= htmlspecialchars($existing_submission['image_path']) ?>" type="video/mp4">
                            Your browser does not support the video tag.
                        </video>
                    <?php else: ?>
                        <img src="<?= htmlspecialchars($existing_submission['image_path']) ?>" alt="Submitted File" style="max-width: 100%; height: 200px; object-fit: cover; border-radius: 5px; margin: 10px 0; border:2px solid #4a90e2;">
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <!-- Submission status -->
            <div style="margin-top:15px; padding:15px; background:#f0f0f0; border-radius:5px; font-size:0.9rem;">
                📊 <strong>Submission Status:</strong> Submitted on <?= date('M j, Y \a\t g:i A', strtotime($existing_submission['submitted_at'])) ?>
                <?php if (!empty($existing_submission['grade'])): ?>
                    <br>📈 <strong>Grade:</strong> <?= $existing_submission['grade'] ?>/<?= $assignment['max_points'] ?>
                <?php endif; ?>
                <?php if (!empty($existing_submission['feedback'])): ?>
                    <br>💬 <strong>Feedback:</strong> <?= nl2br(htmlspecialchars($existing_submission['feedback'])) ?>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <!-- Not submitted yet - show form -->
            <h3>📝 Submit Your Assignment</h3>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="submit_assignment" value="1">
                <textarea name="submission" placeholder="Write your submission here..." rows="6" required></textarea>
                <input type="file" name="submission_image" accept="image/*,video/mp4">
                <small style="color:#666; margin-bottom:10px; display:block;">Optional: Upload an image or MP4 video with your submission</small>
                <button type="submit">📤 Submit Assignment</button>
            </form>
        <?php endif; ?>
    </div>

    <a href="assignment.php" class="back-btn">⬅️ Back to Assignments</a>
</div>
</body>
</html>
