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

// Create community posts tables if they don't exist
$conn->query("
CREATE TABLE IF NOT EXISTS community_posts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    content TEXT NOT NULL,
    image_path VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)
");

$conn->query("
CREATE TABLE IF NOT EXISTS post_likes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    post_id INT NOT NULL,
    user_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_like (post_id, user_id),
    FOREIGN KEY (post_id) REFERENCES community_posts(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)
");

$conn->query("
CREATE TABLE IF NOT EXISTS post_comments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    post_id INT NOT NULL,
    user_id INT NOT NULL,
    content TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (post_id) REFERENCES community_posts(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)
");

$conn->query("
CREATE TABLE IF NOT EXISTS comment_likes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    comment_id INT NOT NULL,
    user_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_comment_like (comment_id, user_id),
    FOREIGN KEY (comment_id) REFERENCES post_comments(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)
");

// Create uploads directory for posts
$post_upload_dir = 'uploads/posts/';
if (!file_exists($post_upload_dir)) {
    mkdir($post_upload_dir, 0777, true);
}

// Handle new post creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['post_content'])) {
    $content = trim($_POST['post_content']);
    $image_path = null;

    // Handle image upload
    if (isset($_FILES['post_image']) && $_FILES['post_image']['error'] === UPLOAD_ERR_OK) {
        $file_name = $_FILES['post_image']['name'];
        $file_tmp = $_FILES['post_image']['tmp_name'];
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

        $allowed_exts = ['jpg', 'jpeg', 'png', 'gif', 'mp4'];
        if (in_array($file_ext, $allowed_exts)) {
            $new_file_name = $user_id . '_' . time() . '_' . uniqid() . '.' . $file_ext;
            $upload_path = $post_upload_dir . $new_file_name;

            if (move_uploaded_file($file_tmp, $upload_path)) {
                $image_path = $upload_path;
            }
        }
    }

    if (!empty($content)) {
        $stmt = $conn->prepare("INSERT INTO community_posts (user_id, content, image_path) VALUES (?, ?, ?)");
        $stmt->bind_param("iss", $user_id, $content, $image_path);
        $stmt->execute();
        $stmt->close();
        header("Location: community.php");
        exit();
    }
}

// Handle post deletion (Admin only)
if (strtolower($role) === 'admin' && isset($_GET['delete_post'])) {
    $post_id = (int)$_GET['delete_post'];
    $stmt = $conn->prepare("DELETE FROM community_posts WHERE id = ?");
    $stmt->bind_param("i", $post_id);
    $stmt->execute();
    $stmt->close();
    header("Location: community.php");
    exit();
}

// Handle post editing (Admin only)
if (strtolower($role) === 'admin' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_post_id'])) {
    $post_id = (int)$_POST['edit_post_id'];
    $content = trim($_POST['edit_content']);

    if (!empty($content)) {
        $stmt = $conn->prepare("UPDATE community_posts SET content = ?, updated_at = NOW() WHERE id = ?");
        $stmt->bind_param("si", $content, $post_id);
        $stmt->execute();
        $stmt->close();
        header("Location: community.php");
        exit();
    }
}

// Handle liking/unliking posts (AJAX and form submission)
if (isset($_POST['like_post'])) {
    $post_id = (int)$_POST['like_post'];

    // Check if user already liked this post
    $check_stmt = $conn->prepare("SELECT id FROM post_likes WHERE post_id = ? AND user_id = ?");
    $check_stmt->bind_param("ii", $post_id, $user_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();

    if ($check_result->num_rows > 0) {
        // Unlike
        $stmt = $conn->prepare("DELETE FROM post_likes WHERE post_id = ? AND user_id = ?");
        $stmt->bind_param("ii", $post_id, $user_id);
        $stmt->execute();
        $action = 'unliked';
    } else {
        // Like
        $stmt = $conn->prepare("INSERT INTO post_likes (post_id, user_id) VALUES (?, ?)");
        $stmt->bind_param("ii", $post_id, $user_id);
        $stmt->execute();
        $action = 'liked';
    }
    $stmt->close();

    // Get updated like count
    $count_stmt = $conn->prepare("SELECT COUNT(*) as count FROM post_likes WHERE post_id = ?");
    $count_stmt->bind_param("i", $post_id);
    $count_stmt->execute();
    $count_result = $count_stmt->get_result();
    $count_row = $count_result->fetch_assoc();
    $new_count = $count_row['count'];
    $count_stmt->close();

    // Check if current user liked it
    $user_liked_stmt = $conn->prepare("SELECT COUNT(*) as user_liked FROM post_likes WHERE post_id = ? AND user_id = ?");
    $user_liked_stmt->bind_param("ii", $post_id, $user_id);
    $user_liked_stmt->execute();
    $user_liked_result = $user_liked_stmt->get_result();
    $user_liked_row = $user_liked_result->fetch_assoc();
    $user_liked = $user_liked_row['user_liked'] > 0;
    $user_liked_stmt->close();

    // Return JSON for AJAX or redirect for form submission
    if (isset($_POST['ajax']) && $_POST['ajax'] == '1') {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'action' => $action,
            'count' => $new_count,
            'user_liked' => $user_liked
        ]);
        exit();
    } else {
        header("Location: community.php");
        exit();
    }
}

// Handle new comment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['comment_content']) && isset($_POST['comment_post_id'])) {
    $post_id = (int)$_POST['comment_post_id'];
    $content = trim($_POST['comment_content']);

    if (!empty($content)) {
        $stmt = $conn->prepare("INSERT INTO post_comments (post_id, user_id, content) VALUES (?, ?, ?)");
        $stmt->bind_param("iis", $post_id, $user_id, $content);
        $stmt->execute();
        $stmt->close();
        header("Location: community.php");
        exit();
    }
}

// Handle liking/unliking comments
if (isset($_POST['like_comment'])) {
    $comment_id = (int)$_POST['like_comment'];

    // Check if user already liked this comment
    $check_stmt = $conn->prepare("SELECT id FROM comment_likes WHERE comment_id = ? AND user_id = ?");
    $check_stmt->bind_param("ii", $comment_id, $user_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();

    if ($check_result->num_rows > 0) {
        // Unlike
        $stmt = $conn->prepare("DELETE FROM comment_likes WHERE comment_id = ? AND user_id = ?");
        $stmt->bind_param("ii", $comment_id, $user_id);
        $stmt->execute();
        $action = 'unliked';
    } else {
        // Like
        $stmt = $conn->prepare("INSERT INTO comment_likes (comment_id, user_id) VALUES (?, ?)");
        $stmt->bind_param("ii", $comment_id, $user_id);
        $stmt->execute();
        $action = 'liked';
    }
    $stmt->close();

    // Get updated like count
    $count_stmt = $conn->prepare("SELECT COUNT(*) as count FROM comment_likes WHERE comment_id = ?");
    $count_stmt->bind_param("i", $comment_id);
    $count_stmt->execute();
    $count_result = $count_stmt->get_result();
    $count_row = $count_result->fetch_assoc();
    $new_count = $count_row['count'];
    $count_stmt->close();

    // Check if current user liked it
    $user_liked_stmt = $conn->prepare("SELECT COUNT(*) as user_liked FROM comment_likes WHERE comment_id = ? AND user_id = ?");
    $user_liked_stmt->bind_param("ii", $comment_id, $user_id);
    $user_liked_stmt->execute();
    $user_liked_result = $user_liked_stmt->get_result();
    $user_liked_row = $user_liked_result->fetch_assoc();
    $user_liked = $user_liked_row['user_liked'] > 0;
    $user_liked_stmt->close();

    // Return JSON for AJAX or redirect for form submission
    if (isset($_POST['ajax']) && $_POST['ajax'] == '1') {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'action' => $action,
            'count' => $new_count,
            'user_liked' => $user_liked
        ]);
        exit();
    } else {
        header("Location: community.php");
        exit();
    }
}

// Fetch community posts with user info, likes, and comments
$posts_stmt = $conn->prepare("
    SELECT
        p.*,
        u.username,
        u.role,
        u.profile_pic,
        (SELECT COUNT(*) FROM post_likes WHERE post_id = p.id) as like_count,
        (SELECT COUNT(*) FROM post_likes WHERE post_id = p.id AND user_id = ?) as user_liked,
        (SELECT COUNT(*) FROM post_comments WHERE post_id = p.id) as comment_count
    FROM community_posts p
    LEFT JOIN users u ON p.user_id = u.id
    ORDER BY p.created_at DESC
    LIMIT 20
");
$posts_stmt->bind_param("i", $user_id);
$posts_stmt->execute();
$posts = $posts_stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Community Posts - Vibely</title>
<link rel="stylesheet" href="vibely.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
<style>
body { font-family: Arial, sans-serif; margin:0; padding:0; background: #f5f6fa; }
.container { max-width: 900px; margin: 20px auto; padding: 10px; }
.header { background:white; padding:20px; border-radius:8px; box-shadow:0 2px 4px rgba(0,0,0,0.05); margin-bottom:20px; text-align:center; }
.back-link { display: inline-block; margin-bottom: 20px; text-decoration: none; color: #4a90e2; font-weight: bold; }
.back-link i { margin-right: 5px; }
.create-post-form { background: #fff; padding: 20px; border-radius: 10px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); margin-bottom: 20px; }
.posts-container { margin-top: 20px; }
.post-item { background: #fff; padding: 20px; margin-bottom: 15px; border-radius: 10px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
.post-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; }
.post-actions { display: flex; align-items: center; gap: 15px; margin-top: 10px; padding-top: 10px; border-top: 1px solid #eee; }
.comments-section { margin-top: 15px; padding-top: 15px; border-top: 1px solid #eee; }
.comment { background: #f8f9fa; padding: 10px; border-radius: 5px; margin-bottom: 8px; }
.admin-controls { display: flex; gap: 5px; }
.no-posts { background: #fff; padding: 40px; border-radius: 10px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); text-align: center; }
</style>
</head>
<body class="<?php echo $theme; ?>">
<div class="container">
    <a href="homepage.php" class="back-link"><i class="fas fa-arrow-left"></i> Back to Homepage</a>

    <div class="header">
        <h1>📰 Community Posts</h1>
        <p>Share and interact with your community</p>
    </div>

    <!-- Create New Post Form (Students, Teachers, and Admins) -->
    <?php if (strtolower($role) === 'student' || strtolower($role) === 'teacher' || strtolower($role) === 'admin'): ?>
        <div class="create-post-form">
            <h3>📝 Share Something</h3>
            <form method="POST" enctype="multipart/form-data">
                <textarea name="post_content" placeholder="What's on your mind?" rows="3" required style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px; margin-bottom: 10px;"></textarea>
                <div style="display: flex; align-items: center; gap: 10px;">
                    <input type="file" name="post_image" id="post_image" accept="image/*,video/*" style="flex: 1;">
                    <button type="submit" style="background: #4a90e2; color: white; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer;">📤 Post</button>
                </div>
                <small style="color: #666; display: block; margin-top: 5px;">Supported formats: JPG, PNG, GIF, MP4</small>

                <!-- Image/Video Preview -->
                <div id="media-preview" style="display: none; margin-top: 15px; padding: 15px; border: 2px dashed #ddd; border-radius: 8px; background: #f9f9f9;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                        <span style="font-weight: bold; color: #666;">📎 Preview</span>
                        <button type="button" id="remove-preview" style="background: #e74c3c; color: white; border: none; padding: 5px 10px; border-radius: 3px; cursor: pointer; font-size: 0.8rem;">✕ Remove</button>
                    </div>
                    <div id="preview-content"></div>
                </div>
            </form>
        </div>
    <?php endif; ?>

    <!-- Display Posts -->
    <?php if ($posts->num_rows > 0): ?>
        <div class="posts-container">
            <?php while ($post = $posts->fetch_assoc()): ?>
                <div class="post-item">
                    <!-- Post Header -->
                    <div class="post-header">
                        <div style="display: flex; align-items: center; gap: 10px;">
                            <?php if (!empty($post['profile_pic']) && file_exists($post['profile_pic'])): ?>
                                <img src="<?php echo htmlspecialchars($post['profile_pic']); ?>" alt="Profile Picture" style="width: 32px; height: 32px; border-radius: 50%; object-fit: cover; border: 2px solid #ddd;">
                            <?php else: ?>
                                <div style="width: 32px; height: 32px; border-radius: 50%; background-color: #ddd; display: flex; align-items: center; justify-content: center; border: 2px solid #ddd;">
                                    <i class="fas fa-user" style="color: #666; font-size: 16px;"></i>
                                </div>
                            <?php endif; ?>
                            <strong style="color: #4a90e2;"><?php echo htmlspecialchars($post['username']); ?></strong>
                            <span style="background: <?php echo strtolower($post['role']) === 'admin' ? '#e74c3c' : (strtolower($post['role']) === 'teacher' ? '#27ae60' : '#4a90e2'); ?>; color: white; padding: 2px 8px; border-radius: 10px; font-size: 0.8rem;">
                                <?php echo htmlspecialchars($post['role']); ?>
                            </span>
                        </div>
                        <div style="display: flex; align-items: center; gap: 10px;">
                            <small style="color: #666;"><?php echo date('M j, Y g:i A', strtotime($post['created_at'])); ?></small>
                            <?php if (strtolower($role) === 'admin'): ?>
                                <div class="admin-controls">
                                    <button onclick="editPost(<?php echo $post['id']; ?>, '<?php echo addslashes($post['content']); ?>')" style="background: #ff9800; color: white; border: none; padding: 5px 10px; border-radius: 3px; cursor: pointer; font-size: 0.8rem;">✏️ Edit</button>
                                    <a href="?delete_post=<?php echo $post['id']; ?>" onclick="return confirm('Are you sure you want to delete this post?')" style="background: #e74c3c; color: white; text-decoration: none; padding: 5px 10px; border-radius: 3px; font-size: 0.8rem;">🗑️ Delete</a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Post Content -->
                    <div style="margin-bottom: 10px; line-height: 1.5;">
                        <?php echo nl2br(htmlspecialchars($post['content'])); ?>
                    </div>

                    <!-- Post Media -->
                    <?php if (!empty($post['image_path']) && file_exists($post['image_path'])): ?>
                        <?php $file_ext = strtolower(pathinfo($post['image_path'], PATHINFO_EXTENSION)); ?>
                        <?php if ($file_ext === 'mp4'): ?>
                            <video controls style="max-width: 100%; height: auto; border-radius: 8px; margin: 10px 0;">
                                <source src="<?php echo htmlspecialchars($post['image_path']); ?>" type="video/mp4">
                                Your browser does not support the video tag.
                            </video>
                        <?php else: ?>
                            <img src="<?php echo htmlspecialchars($post['image_path']); ?>" alt="Post Image" style="max-width: 100%; height: auto; border-radius: 8px; margin: 10px 0;">
                        <?php endif; ?>
                    <?php endif; ?>

                    <!-- Post Actions -->
                    <div class="post-actions">
                        <button class="like-post-btn" data-post-id="<?php echo $post['id']; ?>" style="background: none; border: none; color: <?php echo $post['user_liked'] > 0 ? '#e74c3c' : '#666'; ?>; cursor: pointer; display: flex; align-items: center; gap: 5px;">
                            <i class="fas fa-heart"></i>
                            <span class="like-count"><?php echo $post['like_count']; ?> <?php echo $post['like_count'] == 1 ? 'Like' : 'Likes'; ?></span>
                        </button>
                        <button onclick="showLikesModal('post', <?php echo $post['id']; ?>)" title="See who liked this post" style="background: none; border: none; color: #4a90e2; cursor: pointer; margin-left: 5px;">
                            <i class="fas fa-users"></i>
                        </button>

                        <button onclick="toggleComments(<?php echo $post['id']; ?>)" style="background: none; border: none; color: #666; cursor: pointer; display: flex; align-items: center; gap: 5px;">
                            <i class="fas fa-comment"></i>
                            <span><?php echo $post['comment_count']; ?> <?php echo $post['comment_count'] == 1 ? 'Comment' : 'Comments'; ?></span>
                        </button>
                    </div>

                    <!-- Comments Section -->
                    <div id="comments-<?php echo $post['id']; ?>" class="comments-section" style="display: none;">
                        <!-- Add Comment Form -->
                        <form method="POST" style="margin-bottom: 15px;">
                            <input type="hidden" name="comment_post_id" value="<?php echo $post['id']; ?>">
                            <div style="display: flex; gap: 10px;">
                                <input type="text" name="comment_content" placeholder="Write a comment..." required style="flex: 1; padding: 8px; border: 1px solid #ddd; border-radius: 5px;">
                                <button type="submit" style="background: #4a90e2; color: white; border: none; padding: 8px 15px; border-radius: 5px; cursor: pointer;">💬 Comment</button>
                            </div>
                        </form>

                        <!-- Display Comments -->
                        <?php
                        $comments_stmt = $conn->prepare("
                            SELECT c.*, u.username, u.role, u.profile_pic,
                            (SELECT COUNT(*) FROM comment_likes WHERE comment_id = c.id) as like_count,
                            (SELECT COUNT(*) FROM comment_likes WHERE comment_id = c.id AND user_id = ?) as user_liked
                            FROM post_comments c
                            LEFT JOIN users u ON c.user_id = u.id
                            WHERE c.post_id = ?
                            ORDER BY c.created_at ASC
                        ");
                        $comments_stmt->bind_param("ii", $user_id, $post['id']);
                        $comments_stmt->execute();
                        $comments = $comments_stmt->get_result();

                        while ($comment = $comments->fetch_assoc()):
                        ?>
                            <div class="comment">
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 5px;">
                                    <div style="display: flex; align-items: center; gap: 8px;">
                                        <?php if (!empty($comment['profile_pic']) && file_exists($comment['profile_pic'])): ?>
                                            <img src="<?php echo htmlspecialchars($comment['profile_pic']); ?>" alt="Profile Picture" style="width: 24px; height: 24px; border-radius: 50%; object-fit: cover; border: 1px solid #ddd;">
                                        <?php else: ?>
                                            <div style="width: 24px; height: 24px; border-radius: 50%; background-color: #ddd; display: flex; align-items: center; justify-content: center; border: 1px solid #ddd;">
                                                <i class="fas fa-user" style="color: #666; font-size: 12px;"></i>
                                            </div>
                                        <?php endif; ?>
                                        <strong style="color: #4a90e2; font-size: 0.9rem;"><?php echo htmlspecialchars($comment['username']); ?></strong>
                                        <span style="background: <?php echo strtolower($comment['role']) === 'admin' ? '#e74c3c' : (strtolower($comment['role']) === 'teacher' ? '#27ae60' : '#4a90e2'); ?>; color: white; padding: 1px 6px; border-radius: 8px; font-size: 0.7rem;">
                                            <?php echo htmlspecialchars($comment['role']); ?>
                                        </span>
                                    </div>
                                    <div style="display: flex; align-items: center; gap: 10px;">
                                        <small style="color: #666; font-size: 0.8rem;"><?php echo date('M j, g:i A', strtotime($comment['created_at'])); ?></small>
                                        <button class="like-comment-btn" data-comment-id="<?php echo $comment['id']; ?>" style="background: none; border: none; color: <?php echo $comment['user_liked'] > 0 ? '#e74c3c' : '#666'; ?>; cursor: pointer; display: flex; align-items: center; gap: 5px; font-size: 0.8rem;">
                                            <i class="fas fa-heart"></i>
                                            <span class="like-count"><?php echo $comment['like_count']; ?> <?php echo $comment['like_count'] == 1 ? 'Like' : 'Likes'; ?></span>
                                        </button>
                                        <button onclick="showLikesModal('comment', <?php echo $comment['id']; ?>)" title="See who liked this comment" style="background: none; border: none; color: #4a90e2; cursor: pointer; margin-left: 5px; font-size: 0.8rem;">
                                            <i class="fas fa-users"></i>
                                        </button>
                                    </div>
                                </div>
                                <div style="color: #333;"><?php echo htmlspecialchars($comment['content']); ?></div>
                            </div>
                        <?php endwhile; $comments_stmt->close(); ?>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
    <?php else: ?>
        <div class="no-posts">
            <h3 style="color: #666; margin-bottom: 10px;">📝 No Posts Yet</h3>
            <p style="color: #888;">Be the first to share something with the community!</p>
        </div>
    <?php endif; ?>
</div>

<script>
function toggleComments(postId) {
    const commentsSection = document.getElementById('comments-' + postId);
    commentsSection.style.display = commentsSection.style.display === 'none' ? 'block' : 'none';
}

function editPost(postId, currentContent) {
    const newContent = prompt('Edit your post:', currentContent);
    if (newContent !== null && newContent.trim() !== '' && newContent !== currentContent) {
        // Create a form to submit the edit
        const form = document.createElement('form');
        form.method = 'POST';
        form.style.display = 'none';

        const postIdInput = document.createElement('input');
        postIdInput.type = 'hidden';
        postIdInput.name = 'edit_post_id';
        postIdInput.value = postId;

        const contentInput = document.createElement('input');
        contentInput.type = 'hidden';
        contentInput.name = 'edit_content';
        contentInput.value = newContent.trim();

        form.appendChild(postIdInput);
        form.appendChild(contentInput);
        document.body.appendChild(form);
        form.submit();
    }
}

// Image/Video Preview Functionality
document.addEventListener('DOMContentLoaded', function() {
    const fileInput = document.getElementById('post_image');
    const previewContainer = document.getElementById('media-preview');
    const previewContent = document.getElementById('preview-content');
    const removeButton = document.getElementById('remove-preview');

    if (fileInput && previewContainer && previewContent && removeButton) {
        // Handle file selection
        fileInput.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const fileType = file.type;
                const fileURL = URL.createObjectURL(file);

                // Clear previous preview
                previewContent.innerHTML = '';

                if (fileType.startsWith('image/')) {
                    // Create image preview
                    const img = document.createElement('img');
                    img.src = fileURL;
                    img.style.maxWidth = '100%';
                    img.style.maxHeight = '300px';
                    img.style.borderRadius = '8px';
                    img.style.objectFit = 'cover';
                    previewContent.appendChild(img);
                } else if (fileType.startsWith('video/')) {
                    // Create video preview
                    const video = document.createElement('video');
                    video.src = fileURL;
                    video.style.maxWidth = '100%';
                    video.style.maxHeight = '300px';
                    video.style.borderRadius = '8px';
                    video.controls = true;
                    previewContent.appendChild(video);
                }

                // Show preview container
                previewContainer.style.display = 'block';
            } else {
                // Hide preview if no file selected
                previewContainer.style.display = 'none';
            }
        });

        // Handle remove preview
        removeButton.addEventListener('click', function() {
            // Clear file input
            fileInput.value = '';
            // Clear preview content
            previewContent.innerHTML = '';
            // Hide preview container
            previewContainer.style.display = 'none';
        });
    }

    // Handle post likes
    document.addEventListener('click', function(e) {
        const likeBtn = e.target.closest('.like-post-btn');
        if (likeBtn) {
            e.preventDefault();
            const postId = likeBtn.getAttribute('data-post-id');
            likePost(postId);
        }
    });

    // Handle comment likes
    document.addEventListener('click', function(e) {
        const likeBtn = e.target.closest('.like-comment-btn');
        if (likeBtn) {
            e.preventDefault();
            const commentId = likeBtn.getAttribute('data-comment-id');
            likeComment(commentId);
        }
    });
});

// Function to handle post liking
function likePost(postId) {
    const formData = new FormData();
    formData.append('like_post', postId);
    formData.append('ajax', '1');

    fetch('community.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Update the like count and button appearance
            const likeBtn = document.querySelector(`.like-post-btn[data-post-id="${postId}"]`);
            const likeCountSpan = likeBtn.querySelector('.like-count');

            likeCountSpan.textContent = `${data.count} ${data.count == 1 ? 'Like' : 'Likes'}`;
            likeBtn.style.color = data.user_liked ? '#e74c3c' : '#666';
        }
    })
    .catch(error => console.error('Error:', error));
}

// Function to handle comment liking
function likeComment(commentId) {
    const formData = new FormData();
    formData.append('like_comment', commentId);
    formData.append('ajax', '1');

    fetch('community.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Update the like count and button appearance
            const likeBtn = document.querySelector(`.like-comment-btn[data-comment-id="${commentId}"]`);
            const likeCountSpan = likeBtn.querySelector('.like-count');

            likeCountSpan.textContent = `${data.count} ${data.count == 1 ? 'Like' : 'Likes'}`;
            likeBtn.style.color = data.user_liked ? '#e74c3c' : '#666';
        }
    })
    .catch(error => console.error('Error:', error));
}

// Function to show likes modal
function showLikesModal(type, id) {
    // Create modal
    const modal = document.createElement('div');
    modal.style.cssText = `
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0,0,0,0.5);
        display: flex;
        justify-content: center;
        align-items: center;
        z-index: 1000;
    `;

    const modalContent = document.createElement('div');
    modalContent.style.cssText = `
        background: white;
        padding: 20px;
        border-radius: 10px;
        max-width: 400px;
        width: 90%;
        max-height: 60vh;
        overflow-y: auto;
    `;

    modalContent.innerHTML = `
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
            <h3 style="margin: 0; color: #4a90e2;">People who liked this ${type}</h3>
            <button onclick="closeModal()" style="background: none; border: none; font-size: 20px; cursor: pointer; color: #666;">&times;</button>
        </div>
        <div id="likes-list" style="text-align: center;">
            <p>Loading...</p>
        </div>
    `;

    modal.appendChild(modalContent);
    document.body.appendChild(modal);

    // Fetch likes data
    fetchLikes(type, id);
}

// Function to fetch likes data
function fetchLikes(type, id) {
    fetch(`get_likes.php?type=${type}&id=${id}`, { credentials: 'same-origin' })
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }
        return response.json();
    })
    .then(data => {
        const likesList = document.getElementById('likes-list');

        if (data.success) {
            if (data.likes.length === 0) {
                likesList.innerHTML = `<p style="color: #666;">No likes yet.</p>`;
            } else {
                let html = '';
                data.likes.forEach(like => {
                    html += `
                        <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 10px; padding: 8px; border-radius: 5px; background: #f8f9fa;">
                            ${like.profile_pic ?
                                `<img src="${like.profile_pic}" alt="Profile" style="width: 32px; height: 32px; border-radius: 50%; object-fit: cover;">` :
                                `<div style="width: 32px; height: 32px; border-radius: 50%; background-color: #ddd; display: flex; align-items: center; justify-content: center;">
                                    <i class="fas fa-user" style="color: #666; font-size: 16px;"></i>
                                </div>`
                            }
                            <div>
                                <strong style="color: #4a90e2;">${like.username}</strong>
                                <span style="background: ${like.role.toLowerCase() === 'admin' ? '#e74c3c' : (like.role.toLowerCase() === 'teacher' ? '#27ae60' : '#4a90e2')}; color: white; padding: 2px 6px; border-radius: 8px; font-size: 0.7rem; margin-left: 5px;">
                                    ${like.role}
                                </span>
                            </div>
                        </div>
                    `;
                });
                likesList.innerHTML = html;
            }
        } else {
            likesList.innerHTML = `<p style="color: #e74c3c;">Error loading likes: ${data.error || 'Unknown error'}</p>`;
        }
    })
    .catch(error => {
        console.error('Error fetching likes:', error);
        const likesList = document.getElementById('likes-list');
        likesList.innerHTML = `<p style="color: #e74c3c;">Error loading likes. Please try again.</p>`;
    });
}

// Function to close modal
function closeModal() {
    const modal = document.querySelector('div[style*="position: fixed"]');
    if (modal) {
        modal.remove();
    }
}
</script>
</body>
</html>