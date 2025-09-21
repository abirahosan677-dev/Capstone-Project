<?php
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$current_user = getCurrentUser();
$conn = getDBConnection();

// Fetch user's theme preference
$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT theme FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$theme = $user['theme'] ?? 'light';

// Handle profile picture upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['profile_pic'])) {
    $file = $_FILES['profile_pic'];
    if ($file['error'] === UPLOAD_ERR_OK) {
        $upload_dir = 'uploads/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $allowed_exts = ['jpg', 'jpeg', 'png', 'gif'];
        if (in_array(strtolower($ext), $allowed_exts)) {
            $new_name = $_SESSION['user_id'] . '_' . time() . '.' . $ext;
            $path = $upload_dir . $new_name;
            if (move_uploaded_file($file['tmp_name'], $path)) {
                $stmt = $conn->prepare("UPDATE users SET profile_pic = ? WHERE id = ?");
                $stmt->bind_param("si", $path, $_SESSION['user_id']);
                $stmt->execute();
                // Refresh to show new picture
                header("Location: profile.php");
                exit();
            }
        }
    }
}

// Fetch user's posts
$stmt = $conn->prepare("SELECT * FROM posts WHERE user_id = ? ORDER BY created_at DESC");
$stmt->bind_param("i", $user_id);
$stmt->bind_param("s", $current_user['username']);
$stmt->execute();
$user_posts = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Profile - Vibely</title>
<link rel="stylesheet" href="vibely.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
<style>
body { font-family: Arial, sans-serif; margin:0; padding:0; background: #f5f6fa; }
.container { max-width: 900px; margin: 20px auto; padding: 10px; }
.profile-header { background:white; padding:20px; border-radius:8px; box-shadow:0 2px 4px rgba(0,0,0,0.05); margin-bottom:20px; text-align:center; }
.profile-pic { width:125px; height:125px; border-radius:50%; margin-bottom:10px; }
.profile-info h2 { margin:0; }
.profile-info p { color:#666; }
.edit-profile { margin-top: 20px; }
.edit-profile label { font-weight: bold; }
.edit-profile input[type="file"] { margin: 10px 0; }
.edit-profile button { padding: 5px 10px; background: #4a90e2; color: white; border: none; border-radius: 3px; cursor: pointer; }
.posts-section { background:white; padding:20px; border-radius:8px; box-shadow:0 2px 4px rgba(0,0,0,0.05); }
.posts-section h3 { margin-top:0; }
.post { background:#f9f9f9; padding:10px; margin-bottom:10px; border-radius:5px; }
.post small { color:#666; }
</style>
</head>
<body class="<?php echo $theme; ?>">
<div class="container">
    <a href="homepage.php" style="display: block; margin-bottom: 20px; text-decoration: none; color: #4a90e2;"><i class="fas fa-home"></i> Back to Homepage</a>

    <!-- Profile Header -->
    <div class="profile-header">
        <h2 style="text-align: center; font-weight: bold;">Profile Information</h2>
        <?php if ($current_user['profile_pic']): ?>
            <img src="<?php echo $current_user['profile_pic']; ?>" alt="Profile Picture" class="profile-pic" style="width: 125px; height: 125px; border-radius: 50%; margin-bottom: 10px; object-fit: cover;">
        <?php else: ?>
            <div class="profile-pic default-pic" style="width: 125px; height: 125px; border-radius: 50%; margin-bottom: 10px; background-color: #fff; border: 2px solid #ddd; display: flex; align-items: center; justify-content: center; color: #666; font-size: 36px; margin: 0 auto;">
                <i class="fas fa-user"></i>
            </div>
        <?php endif; ?>
        <div class="profile-info">
            <table style="margin: 0 auto; text-align: left; border-collapse: collapse; border: none;">
                <tr>
                    <td style="padding: 5px 10px;"><strong>Username:</strong></td>
                    <td style="padding: 5px 10px;"><?php echo htmlspecialchars($current_user['username']); ?></td>
                </tr>
                <tr>
                    <td style="padding: 5px 10px;"><strong>Email:</strong></td>
                    <td style="padding: 5px 10px;"><?php echo htmlspecialchars($current_user['email']); ?></td>
                </tr>
                <tr>
                    <td style="padding: 5px 10px;"><strong>Role:</strong></td>
                    <td style="padding: 5px 10px;"><?php echo htmlspecialchars(ucfirst($current_user['role'] ?? 'Student')); ?></td>
                </tr>
                <tr>
                    <td style="padding: 5px 10px;"><strong>Member since:</strong></td>
                    <td style="padding: 5px 10px;"><?php echo date('F Y', strtotime($current_user['created_at'])); ?></td>
                </tr>
            </table>
        </div>

        <!-- Logout Button -->
        <div style="margin-top: 20px;">
            <a href="logout.php" class="logout-btn" style="display: inline-block; padding: 10px 20px; background-color: #e74c3c; color: white; text-decoration: none; border-radius: 5px; font-weight: bold;">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </div>

        <!-- Edit Profile Picture -->
        <div class="edit-profile">
            <form method="POST" enctype="multipart/form-data" id="profilePicForm">
                <button type="button" id="changePicBtn">Change Profile Picture</button>
                <input type="file" name="profile_pic" id="profilePicInput" accept="image/*" style="display:none;" required>
                <div id="previewContainer" style="display:none; margin:10px 0;">
                    <img id="previewing" src="" alt="Preview" style="max-width:100px; max-height:100px; border-radius:50%;">
                </div>
                <button type="submit" id="confirmBtn" style="display:none;">Confirm</button>
            </form>
        </div>

        <script>
            const changePicBtn = document.getElementById('changePicBtn');
            const profilePicInput = document.getElementById('profilePicInput');
            const confirmBtn = document.getElementById('confirmBtn');
            const currentProfilePic = document.querySelector('.profile-pic');

            changePicBtn.addEventListener('click', function() {
                profilePicInput.click();
            });

            profilePicInput.addEventListener('change', function() {
                if (this.files.length > 0) {
                    const file = this.files[0];
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        // Create or update the preview image in place of current profile picture
                        let previewing = document.getElementById('previewing');
                        if (!previewing) {
                            previewing = document.createElement('img');
                            previewing.id = 'previewing';
                            previewing.alt = 'Preview';
                            previewing.className = 'profile-pic'; // Use the same class for consistent styling
                        }
                        previewing.src = e.target.result;
                        // Ensure exact sizing after image loads
                        previewing.onload = function() {
                            this.style.width = '125px';
                            this.style.height = '125px';
                            this.style.objectFit = 'cover';
                            this.style.borderRadius = '50%';
                            this.style.marginBottom = '10px';
                        };

                        // Replace current profile picture with preview
                        if (currentProfilePic) {
                            currentProfilePic.parentNode.replaceChild(previewing, currentProfilePic);
                        }

                        confirmBtn.style.display = 'inline-block';
                        changePicBtn.textContent = 'Change Selection';
                    };
                    reader.readAsDataURL(file);
                } else {
                    // Reset to original state if no file selected
                    confirmBtn.style.display = 'none';
                    changePicBtn.textContent = 'Change Profile Picture';
                }
            });
        </script>
    </div>

    <!-- User's Posts -->
    <div class="posts-section">
        <h3>Your Posts</h3>
        <?php if ($user_posts->num_rows > 0): ?>
            <?php while ($post = $user_posts->fetch_assoc()): ?>
                <div class="post">
                    <p><?php echo htmlspecialchars($post['content']); ?></p>
                    <?php if ($post['image_url']): ?>
                        <img src="<?php echo htmlspecialchars($post['image_url']); ?>" alt="Post image" style="max-width:100%; height:auto;">
                    <?php endif; ?>
                    <small>Posted on <?php echo date('M j, Y g:i A', strtotime($post['Publish_Date'])); ?> - <?php echo $post['Likes']; ?> likes</small>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <p>You haven't posted anything yet.</p>
        <?php endif; ?>
    </div>

</div>
</body>
</html>
