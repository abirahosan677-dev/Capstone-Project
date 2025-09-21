<?php
require_once 'config.php';

header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized - No session']);
    exit();
}

$type = $_GET['type'] ?? '';
$id = (int)($_GET['id'] ?? 0);

if (empty($type) || !$id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid parameters']);
    exit();
}

try {
    $conn = getDBConnection();

    if ($type === 'post') {
        $stmt = $conn->prepare("
            SELECT u.username, u.role, u.profile_pic
            FROM post_likes pl
            JOIN users u ON pl.user_id = u.id
            WHERE pl.post_id = ?
            ORDER BY pl.id DESC
            LIMIT 50
        ");
    } elseif ($type === 'comment') {
        $stmt = $conn->prepare("
            SELECT u.username, u.role, u.profile_pic
            FROM comment_likes cl
            JOIN users u ON cl.user_id = u.id
            WHERE cl.comment_id = ?
            ORDER BY cl.id DESC
            LIMIT 50
        ");
    } else {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid type']);
        exit();
    }

    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();

    $likes = [];
    while ($row = $result->fetch_assoc()) {
        $likes[] = [
            'username' => htmlspecialchars($row['username']),
            'role' => htmlspecialchars($row['role']),
            'profile_pic' => $row['profile_pic'] ? htmlspecialchars($row['profile_pic']) : null
        ];
    }

    echo json_encode([
        'success' => true,
        'likes' => $likes,
        'count' => count($likes)
    ]);

    $stmt->close();
    $conn->close();

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage()
    ]);
}
