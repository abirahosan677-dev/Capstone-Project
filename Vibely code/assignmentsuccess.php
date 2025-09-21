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
$stmt = $conn->prepare("SELECT role FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($role);
$stmt->fetch();
$stmt->close();

// Create tables if they do not exist
$conn->query("
CREATE TABLE IF NOT EXISTS assignments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)
");

$conn->query("
CREATE TABLE IF NOT EXISTS assignment_submissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    assignment_id INT NOT NULL,
    student_id INT NOT NULL,
    content TEXT NOT NULL,
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_submission (assignment_id, student_id)
)
");

// Teacher publishes assignment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $role === 'Teacher' && isset($_POST['title'], $_POST['description'])) {
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);

    if ($title !== '' && $description !== '') {
        $stmt = $conn->prepare("INSERT INTO assignments (title, description, created_by) VALUES (?, ?, ?)");
        if (!$stmt) die("Prepare failed: " . $conn->error);
        $stmt->bind_param("ssi", $title, $description, $user_id);
        $stmt->execute();
        $stmt->close();

        header("Location: assignmentsuccess.php");
        exit();
    }
}

// Student submits solution
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $role === 'Student' && isset($_POST['assignment_id'], $_POST['submission'])) {
    $assignment_id = (int)$_POST['assignment_id'];
    $submission = trim($_POST['submission']);

    if ($submission !== '') {
        $stmt = $conn->prepare("INSERT INTO assignment_submissions (assignment_id, student_id, content) VALUES (?, ?, ?)");
        if (!$stmt) die("Prepare failed: " . $conn->error);
        $stmt->bind_param("iis", $assignment_id, $user_id, $submission);
        $stmt->execute();
        $stmt->close();

        header("Location: assignmentsuccess.php");
        exit();
    }
}

// Fetch all assignments
$result = $conn->query("
SELECT a.*, u.username 
FROM assignments a 
LEFT JOIN users u ON a.created_by = u.id 
ORDER BY created_at DESC
");
if (!$result) die("Query failed: " . $conn->error);
$assignments = [];
while ($row = $result->fetch_assoc()) $assignments[] = $row;

// Fetch submissions
$submissions_by_assignment = [];
if ($role === 'Teacher') {
    $sub_res = $conn->query("
    SELECT s.*, u.username AS student_name 
    FROM assignment_submissions s 
    LEFT JOIN users u ON s.student_id = u.id
    ORDER BY submitted_at DESC
    ");
    if ($sub_res) {
        while ($sub = $sub_res->fetch_assoc()) {
            $submissions_by_assignment[$sub['assignment_id']][] = $sub;
        }
    }
} elseif ($role === 'Student') {
    $student_sub_res = $conn->prepare("
    SELECT assignment_id, content, submitted_at 
    FROM assignment_submissions 
    WHERE student_id = ?
    ");
    $student_sub_res->bind_param("i", $user_id);
    $student_sub_res->execute();
    $res = $student_sub_res->get_result();
    $student_submissions = [];
    while ($sub = $res->fetch_assoc()) {
        $student_submissions[$sub['assignment_id']] = $sub;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Assignments - Vibely</title>
<link rel="stylesheet" href="vibely.css">
<style>
body { font-family: Arial, sans-serif; margin:0; background:#f5f6fa; }
.container { width:90%; max-width:800px; margin:50px auto; }
form { background:#fff; padding:20px; border-radius:8px; box-shadow:0 2px 6px rgba(0,0,0,0.1); margin-bottom:20px; }
input[type=text], textarea { width:100%; padding:10px; margin-bottom:10px; border-radius:5px; border:1px solid #ccc; }
button { padding:10px 15px; border:none; border-radius:5px; background:#4a90e2; color:white; cursor:pointer; }
.assignment { background:#fff; padding:15px; margin-bottom:15px; border-radius:8px; box-shadow:0 2px 4px rgba(0,0,0,0.1); }
.assignment .info { font-size:0.85rem; color:#555; margin-bottom:5px; }
.submission-form { margin-top:10px; }
.submission-form textarea { margin-bottom:5px; }
.submissions { margin-top:10px; background:#f9f9f9; padding:10px; border-radius:5px; }
.submissions div { margin-bottom:5px; font-size:0.9rem; }
.back-btn { display:inline-block; margin-top:10px; color:#4a90e2; text-decoration:none; }
.view-btn { margin-top:5px; display:inline-block; padding:5px 10px; background:#4a90e2; color:white; border-radius:5px; text-decoration:none; font-size:0.85rem; }
.already-submitted { color:#e91e63; font-weight:bold; margin-top:5px; display:block; }
</style>
</head>
<body>
<div class="container">
    <h1>Assignments</h1>

    <!-- Teacher creates assignment -->
    <?php if ($role === 'Teacher'): ?>
        <form method="POST">
            <input type="text" name="title" placeholder="Assignment Title" required>
            <textarea name="description" placeholder="Assignment Description" required></textarea>
            <button type="submit">Publish Assignment</button>
        </form>
    <?php endif; ?>

    <!-- List assignments -->
    <?php foreach ($assignments as $a): ?>
        <div class="assignment">
            <div class="info"><?= htmlspecialchars($a['username'] ?? 'Unknown') ?> - <?= $a['created_at'] ?></div>
            <div class="title"><strong><?= htmlspecialchars($a['title']) ?></strong></div>
            <div class="description"><?= nl2br(htmlspecialchars($a['description'])) ?></div>

            <?php if ($role === 'Student'): ?>
                <?php if (isset($student_submissions[$a['id']])): ?>
                    <span class="already-submitted">Already Submitted</span>
                    <a href="#" class="view-btn" onclick="alert('Your submission: <?= addslashes($student_submissions[$a['id']]['content']) ?>'); return false;">View Submission</a>
                <?php else: ?>
                    <form method="POST" class="submission-form">
                        <input type="hidden" name="assignment_id" value="<?= $a['id'] ?>">
                        <textarea name="submission" placeholder="Your submission..." required></textarea>
                        <button type="submit">Submit Solution</button>
                    </form>
                <?php endif; ?>
            <?php elseif ($role === 'Teacher'): ?>
                <?php if (isset($submissions_by_assignment[$a['id']])): ?>
                    <a href="#" class="view-btn" onclick="let content=''; <?php foreach ($submissions_by_assignment[$a['id']] as $sub): ?>content += '<?= addslashes($sub['student_name']) ?>: <?= addslashes($sub['content']) ?> (<?= $sub['submitted_at'] ?>)\n'; <?php endforeach; ?> alert(content); return false;">View Submissions</a>
                <?php else: ?>
                    <span class="already-submitted">No submissions yet</span>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>

    <a href="homepage.php" class="back-btn">Back to Homepage</a>
</div>
</body>
</html>
