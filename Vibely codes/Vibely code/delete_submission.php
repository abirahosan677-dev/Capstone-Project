<?php
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$conn = getDBConnection();
$user_id = $_SESSION['user_id'];

// Check user role - only admins can delete submissions
$stmt = $conn->prepare("SELECT LOWER(role) FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($role);
$stmt->fetch();
$stmt->close();

if ($role !== 'admin') {
    die("Access denied. Only admins can delete submissions.");
}

// Get submission ID from query
$submission_id = intval($_GET['id'] ?? 0);
if ($submission_id <= 0) die("Invalid submission ID.");

// Fetch submission to get assignment_id for redirect
$stmt = $conn->prepare("SELECT assignment_id FROM assignment_submissions WHERE id = ?");
$stmt->bind_param("i", $submission_id);
$stmt->execute();
$stmt->bind_result($assignment_id);
if (!$stmt->fetch()) {
    $stmt->close();
    die("Submission not found.");
}
$stmt->close();

// Delete submission
$stmt = $conn->prepare("DELETE FROM assignment_submissions WHERE id = ?");
$stmt->bind_param("i", $submission_id);
$stmt->execute();
$stmt->close();

header("Location: view_submissions.php?assignment_id=" . $assignment_id);
exit();
?>
