<?php
require_once 'config.php';

// Redirect if not logged in
if (!isLoggedIn()) {
    header("Location: login.php");
    exit();
}

$current_user = getCurrentUser();
$conn = getDBConnection();

// ----------------------------
// Fetch recent posts with counts
// ----------------------------
$query_posts = "
SELECT 
    p.id, p.user_id, p.content, p.image_url, p.community_id, p.created_at,
    u.username, u.full_name, u.profile_pic,
    COUNT(DISTINCT l.id) AS like_count,
    COUNT(DISTINCT c.id) AS comment_count,
    MAX(CASE WHEN l2.user_id = ? THEN 1 ELSE 0 END) AS user_liked
FROM posts p
JOIN users u ON p.user_id = u.id
LEFT JOIN likes l ON l.post_id = p.id
LEFT JOIN comments c ON c.post_id = p.id
LEFT JOIN likes l2 ON l2.post_id = p.id AND l2.user_id = ?
GROUP BY p.id, p.user_id, p.content, p.image_url, p.community_id, p.created_at, u.username, u.full_name, u.profile_pic
ORDER BY p.created_at DESC
LIMIT 20
";

$stmt_posts = $conn->prepare($query_posts);
if (!$stmt_posts) {
    die("Prepare failed: (" . $conn->errno . ") " . $conn->error);
}

$stmt_posts->bind_param("ii", $_SESSION['user_id'], $_SESSION['user_id']);
$stmt_posts->execute();
$posts = $stmt_posts->get_result();

// ----------------------------
// Function to display "time ago"
// ----------------------------
function timeAgo($datetime) {
    $time = strtotime($datetime);
    $now = time();
    $diff = $now - $time;

    if ($diff < 60) return $diff . ' seconds ago';
    elseif ($diff < 3600) return floor($diff / 60) . ' minutes ago';
    elseif ($diff < 86400) return floor($diff / 3600) . ' hours ago';
    elseif ($diff < 604800) return floor($diff / 86400) . ' days ago';
    else return date('M j, Y', $time);
}

// ----------------------------
// Fetch user communities (for post form dropdown)
// ----------------------------
$stmt_communities = $conn->prepare("
    SELECT c.* 
    FROM communities c
    JOIN community_members cm ON c.id = cm.community_id
    WHERE cm.user_id = ?
");
$stmt_communities->bind_param("i", $_SESSION['user_id']);
$stmt_communities->execute();
$user_communities = $stmt_communities->get_result();

// ----------------------------
// Fetch featured communities
// ----------------------------
$stmt_featured = $conn->prepare("
    SELECT * FROM communities
    WHERE is_public = 1
    ORDER BY member_count DESC
    LIMIT 3
");
$stmt_featured->execute();
$featured_communities = $stmt_featured->get_result();

// ----------------------------
// Fetch upcoming events
// ----------------------------
$stmt_events = $conn->prepare("
    SELECT * FROM events
    WHERE status = 'scheduled' AND start_datetime > NOW()
    ORDER BY start_datetime ASC
    LIMIT 3
");
$stmt_events->execute();
$upcoming_events = $stmt_events->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vibely - Social Media Platform</title>
    <link rel="stylesheet" href="vibely.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar">
        <div class="nav-container">
            <div class="nav-brand"><h1>Vibely</h1></div>
            <div class="nav-links">
                <a href="index.php" class="active"><i class="fas fa-home"></i> Home</a>
                <a href="profile.php"><i class="fas fa-user"></i> Profile</a>
                <a href="communities.php"><i class="fas fa-users"></i> Communities</a>
                <a href="challenges.php"><i class="fas fa-trophy"></i> Challenges</a>
                <a href="events.php"><i class="fas fa-calendar"></i> Events</a>
                <a href="messages.php"><i class="fas fa-comments"></i> Messages</a>
                <a href="search.php"><i class="fas fa-search"></i> Search</a>
                <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </div>
        </div>
    </nav>

    <div class="container">
        <!-- Success messages -->
        <?php if (isset($_GET['success'])): ?>
            <div class="success">
                <?php
                $success_messages = [
                    'community_created' => 'Community created successfully!',
                    'challenge_created' => 'Challenge created successfully!',
                    'event_created' => 'Event created successfully!',
                    'report_submitted' => 'Report submitted successfully!'
                ];
                echo $success_messages[$_GET['success']] ?? 'Operation completed successfully!';
                ?>
            </div>
        <?php endif; ?>

        <div class="main-content">
            <!-- Create Post Section -->
            <div class="create-post">
                <div class="post-header">
                    <img src="<?php echo $current_user['profile_pic'] ?: 'https://via.placeholder.com/40'; ?>" alt="Profile" class="profile-img">
                    <div class="post-info">
                        <h3><?php echo htmlspecialchars($current_user['full_name'] ?: $current_user['username']); ?></h3>
                    </div>
                </div>
                
                <form id="createPostForm" method="POST" action="create_post.php" enctype="multipart/form-data">
                    <textarea name="content" placeholder="What's on your mind?" required></textarea>
                    <input type="text" name="image_url" placeholder="Image URL (optional)">
                    <select name="community_id">
                        <option value="">Post to Timeline</option>
                        <?php while ($community = $user_communities->fetch_assoc()): ?>
                            <option value="<?php echo $community['id']; ?>"><?php echo htmlspecialchars($community['name']); ?></option>
                        <?php endwhile; ?>
                    </select>
                    <button type="submit">Post</button>
                </form>
            </div>

            <!-- Featured Communities -->
            <div class="featured-section">
                <h2>Featured Communities</h2>
                <div class="communities-grid">
                    <?php while ($community = $featured_communities->fetch_assoc()): ?>
                        <div class="community-card">
                            <h3><?php echo htmlspecialchars($community['name']); ?></h3>
                            <p><?php echo htmlspecialchars($community['description']); ?></p>
                            <small><?php echo $community['member_count']; ?> members</small>
                        </div>
                    <?php endwhile; ?>
                </div>
            </div>

            <!-- Upcoming Events -->
            <div class="featured-section">
                <h2>Upcoming Events</h2>
                <div class="events-list">
                    <?php while ($event = $upcoming_events->fetch_assoc()): ?>
                        <div class="event-card">
                            <h3><?php echo htmlspecialchars($event['title']); ?></h3>
                            <p><?php echo htmlspecialchars($event['description']); ?></p>
                            <small><?php echo date('M j, Y g:i A', strtotime($event['start_datetime'])); ?></small>
                        </div>
                    <?php endwhile; ?>
                </div>
            </div>

            <!-- Posts Feed -->
            <div class="posts-feed">
                <h2>Recent Posts</h2>
                <?php if ($posts->num_rows > 0): ?>
                    <?php while ($post = $posts->fetch_assoc()): ?>
                        <div class="post" data-post-id="<?php echo $post['id']; ?>">
                            <div class="post-header">
                                <img src="<?php echo $post['profile_pic'] ?: 'https://via.placeholder.com/40'; ?>" alt="Profile" class="profile-img">
                                <div class="post-info">
                                    <h3><?php echo htmlspecialchars($post['full_name'] ?: $post['username']); ?></h3>
                                    <span class="post-time"><?php echo timeAgo($post['created_at']); ?></span>
                                    <?php if ($post['community_id']): ?>
                                        <small>Posted in Community</small>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="post-content">
                                <p><?php echo nl2br(htmlspecialchars($post['content'])); ?></p>
                                <?php if ($post['image_url']): ?>
                                    <img src="<?php echo $post['image_url']; ?>" alt="Post image" class="post-image">
                                <?php endif; ?>
                            </div>
                            
                            <div class="post-actions">
                                <button class="like-btn <?php echo $post['user_liked'] ? 'liked' : ''; ?>">
                                    <i class="fas fa-heart"></i> 
                                    <span><?php echo $post['like_count']; ?></span>
                                </button>
                                <button class="comment-btn">
                                    <i class="fas fa-comment"></i> 
                                    <span><?php echo $post['comment_count']; ?></span>
                                </button>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <p>No posts yet. Be the first to post!</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="js/vibely.js"></script>
</body>
</html>
