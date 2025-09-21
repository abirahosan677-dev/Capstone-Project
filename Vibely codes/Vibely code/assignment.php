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

// Create assignments table with optional image support
$conn->query("
CREATE TABLE IF NOT EXISTS assignments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    image_path VARCHAR(255) NULL,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    due_date DATE,
    subject VARCHAR(100),
    max_points INT DEFAULT 100
)
");

// Create assignment submissions table with optional image support
$conn->query("
CREATE TABLE IF NOT EXISTS assignment_submissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    assignment_id INT NOT NULL,
    student_id INT NOT NULL,
    content TEXT,
    image_path VARCHAR(255) NULL,
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    grade VARCHAR(10) NULL,
    feedback TEXT NULL
)
");

// Create uploads directory if it doesn't exist
$upload_dir = 'uploads/assignments/';
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// Handle new assignment creation (Teachers only)
if (strtolower($role) === 'teacher' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['title'], $_POST['description'])) {
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $subject = trim($_POST['subject'] ?? '');
    $due_date = trim($_POST['due_date'] ?? '');
    $max_points = (int)($_POST['max_points'] ?? 100);

    $image_path = null;

    // Handle image upload
    if (isset($_FILES['assignment_image']) && $_FILES['assignment_image']['error'] === UPLOAD_ERR_OK) {
        $file_name = $_FILES['assignment_image']['name'];
        $file_tmp = $_FILES['assignment_image']['tmp_name'];
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

        // Validate file type
        $allowed_exts = ['jpg', 'jpeg', 'png', 'gif', 'mp4'];
        if (in_array($file_ext, $allowed_exts)) {
            $new_file_name = $user_id . '_' . time() . '_' . uniqid() . '.' . $file_ext;
            $upload_path = $upload_dir . $new_file_name;

            if (move_uploaded_file($file_tmp, $upload_path)) {
                $image_path = $upload_path;
            }
        }
    }

    if ($title !== '' && $description !== '') {
        $stmt = $conn->prepare("INSERT INTO assignments 
            (title, description, image_path, created_by, due_date, subject, max_points) 
            VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sssisss", $title, $description, $image_path, $user_id, $due_date, $subject, $max_points);
        $stmt->execute();
        $stmt->close();

        header("Location: assignmentsuccess.php");
        exit();
    } else {
        $error = "Title and description cannot be empty.";
    }
}

// Handle assignment editing (Teachers and Admins)
if (($role === 'Teacher' || $role === 'Admin') && isset($_POST['edit_assignment'])) {
    $assignment_id = (int)$_POST['assignment_id'];
    $title = trim($_POST['edit_title']);
    $description = trim($_POST['edit_description']);
    $subject = trim($_POST['edit_subject'] ?? '');
    $due_date = trim($_POST['edit_due_date'] ?? '');
    $max_points = (int)($_POST['edit_max_points'] ?? 100);

    if ($title !== '' && $description !== '') {
        $stmt = $conn->prepare("UPDATE assignments SET title=?, description=?, subject=?, due_date=?, max_points=? WHERE id=? AND (created_by=? OR ?='Admin')");
        $stmt->bind_param("ssssisi", $title, $description, $subject, $due_date, $max_points, $assignment_id, $user_id, $role);
        $stmt->execute();
        $stmt->close();
        header("Location: assignment.php");
        exit();
    }
}

// Handle assignment deletion (Admins only)
if ($role === 'Admin' && isset($_GET['delete_assignment'])) {
    $assignment_id = (int)$_GET['delete_assignment'];
    $stmt = $conn->prepare("DELETE FROM assignments WHERE id=?");
    $stmt->bind_param("i", $assignment_id);
    $stmt->execute();
    $stmt->close();
    header("Location: assignment.php");
    exit();
}



// Fetch assignments with student's submission details
$stmt = $conn->prepare("
    SELECT a.*, u.username AS creator_name,
    s.id AS submission_id, s.submitted_at, s.content AS student_submission
    FROM assignments a
    LEFT JOIN users u ON a.created_by = u.id
    LEFT JOIN assignment_submissions s ON a.id = s.assignment_id AND s.student_id = ?
    ORDER BY a.created_at DESC
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

$assignments = [];
while ($row = $result->fetch_assoc()) {
    $assignments[] = $row;
}
$stmt->close();
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
.container { width:90%; max-width:900px; margin:50px auto; }
form { background:#fff; padding:20px; border-radius:8px; box-shadow:0 2px 6px rgba(0,0,0,0.1); margin-bottom:20px; }
input[type=text], input[type=date], input[type=number], textarea { width:100%; padding:10px; margin-bottom:10px; border-radius:5px; border:1px solid #ccc; }
input[type=file] { margin-bottom:10px; }
button { padding:10px 15px; border:none; border-radius:5px; background:#4a90e2; color:white; cursor:pointer; margin-right:5px; }
button:hover { background:#357ABD; }
.assignment { background:#fff; padding:20px; margin-bottom:20px; border-radius:8px; box-shadow:0 2px 4px rgba(0,0,0,0.1); position:relative; }
.assignment .info { font-size:0.85rem; color:#555; margin-bottom:10px; }
.assignment .meta { display:flex; justify-content:space-between; align-items:center; margin-bottom:10px; }
.assignment .subject { background:#e8f4fd; color:#4a90e2; padding:3px 8px; border-radius:12px; font-size:0.8rem; }
.assignment .due-date { color:#e91e63; font-weight:bold; }
.assignment-image { max-width:100%; height:200px; object-fit:cover; border-radius:5px; margin:10px 0; }
.submission-image { max-width:100%; height:150px; object-fit:cover; border-radius:5px; margin:10px 0; }
.error { color:#e91e63; margin-bottom:10px; background:#ffebee; padding:10px; border-radius:5px; }
.success { color:#4caf50; margin-bottom:10px; background:#e8f5e8; padding:10px; border-radius:5px; }
.submission-form { margin-top:15px; padding:15px; background:#f8f9fa; border-radius:5px; }
.submitted-count { font-size:0.8rem; color:#888; margin-top:10px; }
.student-submission { margin-top:10px; padding:10px; background:#f0f0f0; border-radius:5px; font-size:0.9rem; }
.back-btn { display:inline-block; margin-top:20px; color:#4a90e2; text-decoration:none; padding:10px 20px; border:1px solid #4a90e2; border-radius:5px; }
.back-btn:hover { background:#4a90e2; color:white; }
.admin-controls { position:absolute; top:10px; right:10px; display:flex; gap:5px; }
.edit-btn { background:#ff9800; border:none; border-radius:5px; color:white; cursor:pointer; padding:5px 10px; font-size:0.9rem; }
.edit-btn:hover { background:#f57c00; }
.delete-btn { background:#f44336; border:none; border-radius:5px; color:white; cursor:pointer; padding:5px 10px; font-size:0.9rem; text-decoration:none; display:inline-block; }
.delete-btn:hover { background:#d32f2f; text-decoration:none; }
.edit-form { margin-top:15px; padding:15px; background:#fff3e0; border-radius:5px; border-left:4px solid #ff9800; }
.role-badge { background:#4a90e2; color:white; padding:5px 10px; border-radius:15px; font-size:0.8rem; margin-bottom:15px; display:inline-block; }
</style>
</head>
<body>
<div class="container">
    <h1>Assignments</h1>
    <div class="role-badge">Role: <?= htmlspecialchars($role) ?></div>

    <?php if (!empty($error)): ?>
        <div class="error">⚠️ <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if (strtolower($role) === 'teacher'): ?>
        <!-- Teacher: Create Assignment -->
        <form method="POST" enctype="multipart/form-data">
            <h3>📝 Create New Assignment</h3>
            <input type="text" name="title" placeholder="Assignment Title" required>
            <textarea name="description" placeholder="Assignment Description" rows="4" required></textarea>
            <input type="text" name="subject" placeholder="Subject (e.g., Mathematics, Science)">
            <input type="date" name="due_date" placeholder="Due Date">
            <input type="number" name="max_points" placeholder="Maximum Points" value="100" min="1">
            <input type="file" name="assignment_image" accept="image/*,video/mp4">
            <small style="color:#666; margin-bottom:10px; display:block;">Supported formats: JPG, PNG, GIF, MP4</small>
            <button type="submit">📤 Create Assignment</button>
        </form>
    <?php endif; ?>

    <?php if (strtolower($role) === 'admin'): ?>
        <div style="background:#fff; padding:15px; border-radius:8px; margin-bottom:20px; border-left:4px solid #f44336;">
            <h3>🛠️ Admin Controls</h3>
            <p>You have full access to manage all assignments and view all submissions.</p>
        </div>
    <?php endif; ?>

    <!-- List assignments -->
    <?php foreach ($assignments as $a): ?>
        <div class="assignment">
            <?php if (strtolower($role) === 'admin'): ?>
                <div class="admin-controls">
                    <button class="edit-btn" onclick="toggleEditForm(<?= $a['id'] ?>)">✏️ Edit</button>
                    <a href="?delete_assignment=<?= $a['id'] ?>" class="delete-btn" onclick="return confirm('Are you sure you want to delete this assignment?')">🗑️ Delete</a>
                </div>
            <?php elseif (strtolower($role) === 'teacher' && $a['created_by'] == $user_id): ?>
                <div class="admin-controls">
                    <button class="edit-btn" onclick="toggleEditForm(<?= $a['id'] ?>)">✏️ Edit</button>
                </div>
            <?php endif; ?>

            <div class="meta">
                <div class="info">
                    👤 <?= htmlspecialchars($a['creator_name'] ?? 'Unknown') ?> • 📅 <?= date('M j, Y', strtotime($a['created_at'])) ?>
                </div>
                <?php if (!empty($a['subject'])): ?>
                    <div class="subject">📚 <?= htmlspecialchars($a['subject']) ?></div>
                <?php endif; ?>
            </div>

            <h3>📖 <?= htmlspecialchars($a['title']) ?></h3>
            <div class="description"><?= nl2br(htmlspecialchars($a['description'])) ?></div>

            <?php if (!empty($a['image_path']) && file_exists($a['image_path'])): ?>
                <?php $file_ext = strtolower(pathinfo($a['image_path'], PATHINFO_EXTENSION)); ?>
                <?php if ($file_ext === 'mp4'): ?>
                    <video controls style="max-width: 100%; height: 200px; object-fit: cover; border-radius: 5px; margin: 10px 0;">
                        <source src="<?= htmlspecialchars($a['image_path']) ?>" type="video/mp4">
                        Your browser does not support the video tag.
                    </video>
                <?php else: ?>
                    <img src="<?= htmlspecialchars($a['image_path']) ?>" alt="Assignment Image" class="assignment-image">
                <?php endif; ?>
            <?php endif; ?>

            <?php if (!empty($a['due_date'])): ?>
                <div class="due-date">⏰ Due: <?= date('M j, Y', strtotime($a['due_date'])) ?></div>
            <?php endif; ?>

            <?php if (!empty($a['max_points'])): ?>
                <div style="color:#666; font-size:0.9rem;">📊 Points: <?= $a['max_points'] ?></div>
            <?php endif; ?>

            <!-- Edit Form (hidden by default) -->
            <div id="edit-form-<?= $a['id'] ?>" class="edit-form" style="display:none;">
                <form method="POST">
                    <input type="hidden" name="edit_assignment" value="1">
                    <input type="hidden" name="assignment_id" value="<?= $a['id'] ?>">
                    <input type="text" name="edit_title" value="<?= htmlspecialchars($a['title']) ?>" required>
                    <textarea name="edit_description" rows="3" required><?= htmlspecialchars($a['description']) ?></textarea>
                    <input type="text" name="edit_subject" value="<?= htmlspecialchars($a['subject'] ?? '') ?>" placeholder="Subject">
                    <input type="date" name="edit_due_date" value="<?= $a['due_date'] ?? '' ?>">
                    <input type="number" name="edit_max_points" value="<?= $a['max_points'] ?? 100 ?>" min="1">
                    <button type="submit">💾 Save Changes</button>
                    <button type="button" onclick="toggleEditForm(<?= $a['id'] ?>)">❌ Cancel</button>
                </form>
            </div>

            <?php if (strtolower($role) === 'student'): ?>
                <?php if (empty($a['submission_id'])): ?>
                    <a href="submit_assignment.php?assignment_id=<?= $a['id'] ?>" class="back-btn" style="display:inline-block; margin-top:10px;">📤 Upload Submission</a>
                <?php else: ?>
                    <div class="submitted-count" style="color:#4caf50; font-weight:bold; margin-top:10px;">
                        ✅ You submitted this assignment on <?= date('M j, Y \a\t g:i A', strtotime($a['submitted_at'])) ?>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <!-- Teacher/Admin: View submissions -->
                <?php
                    $stmt2 = $conn->prepare("SELECT COUNT(*) AS total_submissions FROM assignment_submissions WHERE assignment_id=?");
                    $stmt2->bind_param("i", $a['id']);
                    $stmt2->execute();
                    $res2 = $stmt2->get_result()->fetch_assoc();
                    $total_submissions = $res2['total_submissions'] ?? 0;
                    $stmt2->close();
                ?>
                <div class="submitted-count">
                    👥 Student Submissions: <?= $total_submissions ?>
                    <a href="view_submissions.php?assignment_id=<?= $a['id'] ?>" style="margin-left:10px; color:#4a90e2;">👁️ View All Submissions</a>
                </div>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>

    <?php if (empty($assignments)): ?>
        <div class="assignment" style="text-align: center; padding: 40px;">
            <h3 style="color: #666; margin-bottom: 10px;">📝 No Assignments Posted</h3>
            <p style="color: #888;">There are currently no assignments available. Please check back later or contact your teacher.</p>
        </div>
    <?php endif; ?>

    <a href="homepage.php" class="back-btn">🏠 Back to Homepage</a>
</div>

<script>
function toggleEditForm(assignmentId) {
    const form = document.getElementById('edit-form-' + assignmentId);
    form.style.display = form.style.display === 'none' ? 'block' : 'none';
}
</script>
</body>
</html>
