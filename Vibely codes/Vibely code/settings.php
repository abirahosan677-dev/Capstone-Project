<?php
require_once 'config.php';

// Redirect to login if user is not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$conn = getDBConnection();
if (!$conn) {
    die("Database connection failed.");
}

$user_id = $_SESSION['user_id'];

// Translations
$translations = [
    'en' => [
        'Settings' => 'Settings',
        'Back to Homepage' => 'Back to Homepage',
        'Account Settings' => 'Account Settings',
        'Update Profile Information' => 'Update Profile Information',
        'Username' => 'Username',
        'Email' => 'Email',
        'Update Profile' => 'Update Profile',
        'Change Password' => 'Change Password',
        'Current Password' => 'Current Password',
        'New Password' => 'New Password',
        'Confirm New Password' => 'Confirm New Password',
        'Privacy Settings' => 'Privacy Settings',
        'Profile Visibility' => 'Profile Visibility',
        'Public' => 'Public',
        'Friends Only' => 'Friends Only',
        'Private' => 'Private',
        'Post Visibility' => 'Post Visibility',
        'Update Privacy' => 'Update Privacy',
        'Preferences' => 'Preferences',
        'Theme' => 'Theme',
        'Light' => 'Light',
        'Dark' => 'Dark',
        'Language' => 'Language',
        'English' => 'English',
        'Malay' => 'Malay',
        'Mandarin' => 'Mandarin',
        'Update Preferences' => 'Update Preferences',
        'Notifications' => 'Notifications',
        'Enabled' => 'Enabled',
        'Disabled' => 'Disabled',
        'Enable' => 'Enable',
        'Disable' => 'Disable',
        'Security' => 'Security',
        'Two-Factor Authentication' => 'Two-Factor Authentication',
        'Enable 2FA' => 'Enable 2FA',
        'Update 2FA' => 'Update 2FA',
        'Data & Support' => 'Data & Support',
        'Export Your Data' => 'Export Your Data',
        'Download My Data' => 'Download My Data',
        'Help & Support' => 'Help & Support',
        'Need help?' => 'Need help?',
        'Contact Support' => 'Contact Support',
        'or visit our' => 'or visit our',
        'FAQ' => 'FAQ',
        'Delete Account' => 'Delete Account',
        'Are you sure you want to delete your account? This action cannot be undone.' => 'Are you sure you want to delete your account? This action cannot be undone.',
        'Notification settings updated!' => 'Notification settings updated!',
        'Preferences updated!' => 'Preferences updated!',
    ],
    'ms' => [
        'Settings' => 'Tetapan',
        'Back to Homepage' => 'Kembali ke Laman Utama',
        'Account Settings' => 'Tetapan Akaun',
        'Update Profile Information' => 'Kemas Kini Maklumat Profil',
        'Username' => 'Nama Pengguna',
        'Email' => 'Emel',
        'Update Profile' => 'Kemas Kini Profil',
        'Change Password' => 'Tukar Kata Laluan',
        'Current Password' => 'Kata Laluan Semasa',
        'New Password' => 'Kata Laluan Baru',
        'Confirm New Password' => 'Sahkan Kata Laluan Baru',
        'Privacy Settings' => 'Tetapan Privasi',
        'Profile Visibility' => 'Keterlihatan Profil',
        'Public' => 'Awam',
        'Friends Only' => 'Sahaja Rakan',
        'Private' => 'Persendirian',
        'Post Visibility' => 'Keterlihatan Pos',
        'Update Privacy' => 'Kemas Kini Privasi',
        'Preferences' => 'Keutamaan',
        'Theme' => 'Tema',
        'Light' => 'Cerah',
        'Dark' => 'Gelap',
        'Language' => 'Bahasa',
        'English' => 'Inggeris',
        'Malay' => 'Melayu',
        'Mandarin' => 'Mandarin',
        'Update Preferences' => 'Kemas Kini Keutamaan',
        'Notifications' => 'Pemberitahuan',
        'Enabled' => 'Didayakan',
        'Disabled' => 'Dilumpuhkan',
        'Enable' => 'Dayakan',
        'Disable' => 'Lumpuhkan',
        'Security' => 'Keselamatan',
        'Two-Factor Authentication' => 'Pengesahan Dua Faktor',
        'Enable 2FA' => 'Dayakan 2FA',
        'Update 2FA' => 'Kemas Kini 2FA',
        'Data & Support' => 'Data & Sokongan',
        'Export Your Data' => 'Eksport Data Anda',
        'Download My Data' => 'Muat Turun Data Saya',
        'Help & Support' => 'Bantuan & Sokongan',
        'Need help?' => 'Perlukan bantuan?',
        'Contact Support' => 'Hubungi Sokongan',
        'or visit our' => 'atau lawati',
        'FAQ' => 'Soalan Lazim',
        'Delete Account' => 'Padam Akaun',
        'Are you sure you want to delete your account? This action cannot be undone.' => 'Adakah anda pasti mahu memadam akaun anda? Tindakan ini tidak boleh dibuat asal.',
        'Notification settings updated!' => 'Tetapan pemberitahuan dikemas kini!',
        'Preferences updated!' => 'Keutamaan dikemas kini!',
    ],
    'zh' => [
        'Settings' => '设置',
        'Back to Homepage' => '返回主页',
        'Account Settings' => '账户设置',
        'Update Profile Information' => '更新个人资料',
        'Username' => '用户名',
        'Email' => '电子邮件',
        'Update Profile' => '更新资料',
        'Change Password' => '更改密码',
        'Current Password' => '当前密码',
        'New Password' => '新密码',
        'Confirm New Password' => '确认新密码',
        'Privacy Settings' => '隐私设置',
        'Profile Visibility' => '资料可见性',
        'Public' => '公开',
        'Friends Only' => '仅好友',
        'Private' => '私人',
        'Post Visibility' => '帖子可见性',
        'Update Privacy' => '更新隐私',
        'Preferences' => '偏好',
        'Theme' => '主题',
        'Light' => '浅色',
        'Dark' => '深色',
        'Language' => '语言',
        'English' => '英语',
        'Malay' => '马来语',
        'Mandarin' => '普通话',
        'Update Preferences' => '更新偏好',
        'Notifications' => '通知',
        'Enabled' => '启用',
        'Disabled' => '禁用',
        'Enable' => '启用',
        'Disable' => '禁用',
        'Security' => '安全',
        'Two-Factor Authentication' => '双因素认证',
        'Enable 2FA' => '启用2FA',
        'Update 2FA' => '更新2FA',
        'Data & Support' => '数据与支持',
        'Export Your Data' => '导出您的数据',
        'Download My Data' => '下载我的数据',
        'Help & Support' => '帮助与支持',
        'Need help?' => '需要帮助？',
        'Contact Support' => '联系支持',
        'or visit our' => '或访问我们的',
        'FAQ' => '常见问题',
        'Delete Account' => '删除账户',
        'Are you sure you want to delete your account? This action cannot be undone.' => '您确定要删除您的账户吗？此操作无法撤销。',
        'Notification settings updated!' => '通知设置已更新！',
        'Preferences updated!' => '偏好已更新！',
    ],
];

// Translation function
function __($key, $lang) {
    global $translations;
    return $translations[$lang][$key] ?? $key;
}

// Ensure the column exists, create if not
$result = $conn->query("SHOW COLUMNS FROM users LIKE 'notifications_enabled'");
if ($result->num_rows == 0) {
    $conn->query("ALTER TABLE users ADD COLUMN notifications_enabled TINYINT(1) DEFAULT 1");
}
$result = $conn->query("SHOW COLUMNS FROM users LIKE 'theme'");
if ($result->num_rows == 0) {
    $conn->query("ALTER TABLE users ADD COLUMN theme VARCHAR(10) DEFAULT 'light'");
}
$result = $conn->query("SHOW COLUMNS FROM users LIKE 'language'");
if ($result->num_rows == 0) {
    $conn->query("ALTER TABLE users ADD COLUMN language VARCHAR(10) DEFAULT 'en'");
}

// Fetch user data safely
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
if (!$stmt) {
    die("Database prepare error: " . $conn->error);
}

$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$current_user = $result->fetch_assoc();
if (!$current_user) {
    die("User not found.");
}

// Handle notifications toggle
$success = "";
if (isset($_POST['update_notifications'])) {
    $notifications = $_POST['update_notifications'] === 'enable' ? 1 : 0;
    $update_stmt = $conn->prepare("UPDATE users SET notifications_enabled = ? WHERE id = ?");
    if (!$update_stmt) {
        die("Update prepare error: " . $conn->error);
    }
    $update_stmt->bind_param("ii", $notifications, $user_id);
    if ($update_stmt->execute()) {
        $success = __('Notification settings updated!', $current_user['language']);
        $current_user['notifications_enabled'] = $notifications;
    }
}

// Handle preferences update
if (isset($_POST['update_preferences'])) {
    $theme = $_POST['theme'];
    $language = $_POST['language'];
    $update_stmt = $conn->prepare("UPDATE users SET theme = ?, language = ? WHERE id = ?");
    if (!$update_stmt) {
        die("Update prepare error: " . $conn->error);
    }
    $update_stmt->bind_param("ssi", $theme, $language, $user_id);
    if ($update_stmt->execute()) {
        $current_user['theme'] = $theme;
        $current_user['language'] = $language;
        $success = __('Preferences updated!', $current_user['language']);
    }
}

// Handle account deletion
if (isset($_POST['delete_account'])) {
    $delete_stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
    if (!$delete_stmt) {
        die("Delete prepare error: " . $conn->error);
    }
    $delete_stmt->bind_param("i", $user_id);
    if ($delete_stmt->execute()) {
        session_destroy();
        header("Location: goodbye.php"); // Redirect after deletion
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="<?php echo $current_user['language']; ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo __('Settings', $current_user['language']); ?> - Vibely</title>
<link rel="stylesheet" href="vibely.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
<style>
.auth-container {
    display: flex;
    justify-content: flex-start;
    align-items: flex-start;
    padding-top: 50px;
    min-height: 100vh;
    background-color: #f5f6fa;
}
.auth-form {
    width: 90%;
    max-width: 600px;
    margin: 0 auto;
    background: #fff;
    padding: 25px;
    border-radius: 10px;
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
}
.auth-form h1 {
    margin-bottom: 20px;
    text-align: center;
}
.setting-group {
    margin-bottom: 20px;
}
.setting-group label {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-weight: bold;
    margin-bottom: 10px;
}
.setting-group input[type="checkbox"] {
    width: 20px;
    height: 20px;
}
.button {
    display: inline-block;
    padding: 10px 15px;
    background-color: #4a90e2;
    color: white;
    border-radius: 5px;
    border: none;
    cursor: pointer;
    text-decoration: none;
}
.delete-btn {
    background-color: #e74c3c;
}
.enable-btn {
    background-color: green !important;
}
.disable-btn {
    background-color: red !important;
}
.success-message {
    color: green;
    text-align: center;
    margin-bottom: 15px;
}
.support-link {
    color: #4a90e2;
    text-decoration: underline;
    cursor: pointer;
}
.setting-section {
    border-bottom: 1px solid #ddd;
    padding-bottom: 20px;
    margin-bottom: 20px;
}
.setting-section h2 {
    color: #4a90e2;
    margin-bottom: 15px;
    font-size: 1.2em;
}
.setting-section h3 {
    margin-bottom: 10px;
    font-size: 1em;
    color: #333;
}
.setting-group input[type="text"],
.setting-group input[type="email"],
.setting-group input[type="password"],
.setting-group select {
    width: 100%;
    padding: 8px;
    margin-bottom: 10px;
    border: 1px solid #ccc;
    border-radius: 4px;
    box-sizing: border-box;
}
.setting-group label {
    display: block;
    margin-bottom: 5px;
}
.dark {
    background-color: #333;
    color: #fff;
}
.dark .auth-container {
    background-color: #333;
}
.dark .auth-form {
    background: #444;
    color: #fff;
}
.dark .setting-section h2 {
    color: #fff;
}
.dark .setting-section h3 {
    color: #fff;
}
.dark .success-message {
    color: #0f0;
}
.dark .support-link {
    color: #4a90e2;
}
.dark .button {
    background-color: #555;
    color: #fff;
}
.dark .enable-btn {
    background-color: green !important;
}
.dark .disable-btn {
    background-color: red !important;
}
</style>
</head>
<body class="<?php echo $current_user['theme']; ?>">
<div class="auth-container">
    <div class="auth-form">
        <h1><?php echo __('Settings', $current_user['language']); ?></h1>

        <!-- Back to Homepage Button -->
        <a href="homepage.php" class="button" style="color: white; background-color: #4a90e2; margin-bottom: 15px; display: inline-block;">
            <i class="fas fa-arrow-left"></i>
            <?php echo __('Back to Homepage', $current_user['language']); ?>
        </a>

        <?php if ($success): ?>
            <div class="success-message"><?php echo $success; ?></div>
        <?php endif; ?>

        <!-- Account Settings -->
        <div class="setting-section">
            <h2><i class="fas fa-user"></i> <?php echo __('Account Settings', $current_user['language']); ?></h2>
            <div class="setting-group">
                <h3><?php echo __('Update Profile Information', $current_user['language']); ?></h3>
                <form method="POST">
                    <label for="username"><?php echo __('Username', $current_user['language']); ?>:</label>
                    <input type="text" name="username" value="<?php echo htmlspecialchars($current_user['username']); ?>" required>
                    <label for="email"><?php echo __('Email', $current_user['language']); ?>:</label>
                    <input type="email" name="email" value="<?php echo htmlspecialchars($current_user['email']); ?>" required>
                    <button type="submit" name="update_profile" class="button"><?php echo __('Update Profile', $current_user['language']); ?></button>
                </form>
            </div>
            <div class="setting-group">
                <h3><?php echo __('Change Password', $current_user['language']); ?></h3>
                <form method="POST">
                    <label for="current_password"><?php echo __('Current Password', $current_user['language']); ?>:</label>
                    <input type="password" name="current_password" required>
                    <label for="new_password"><?php echo __('New Password', $current_user['language']); ?>:</label>
                    <input type="password" name="new_password" required>
                    <label for="confirm_password"><?php echo __('Confirm New Password', $current_user['language']); ?>:</label>
                    <input type="password" name="confirm_password" required>
                    <button type="submit" name="change_password" class="button"><?php echo __('Change Password', $current_user['language']); ?></button>
                </form>
            </div>
        </div>

        <!-- Privacy Settings -->
        <div class="setting-section">
            <h2><i class="fas fa-shield-alt"></i> <?php echo __('Privacy Settings', $current_user['language']); ?></h2>
            <div class="setting-group">
                <form method="POST">
                    <label for="profile_visibility">
                        <?php echo __('Profile Visibility', $current_user['language']); ?>
                        <select name="profile_visibility">
                            <option value="public"><?php echo __('Public', $current_user['language']); ?></option>
                            <option value="friends"><?php echo __('Friends Only', $current_user['language']); ?></option>
                            <option value="private"><?php echo __('Private', $current_user['language']); ?></option>
                        </select>
                    </label>
                    <label for="post_visibility">
                        <?php echo __('Post Visibility', $current_user['language']); ?>
                        <select name="post_visibility">
                            <option value="public"><?php echo __('Public', $current_user['language']); ?></option>
                            <option value="friends"><?php echo __('Friends Only', $current_user['language']); ?></option>
                        </select>
                    </label>
                    <button type="submit" name="update_privacy" class="button"><?php echo __('Update Privacy', $current_user['language']); ?></button>
                </form>
            </div>
        </div>

        <!-- Preferences -->
        <div class="setting-section">
            <h2><i class="fas fa-cog"></i> <?php echo __('Preferences', $current_user['language']); ?></h2>
            <div class="setting-group">
                <form method="POST">
                    <label for="theme">
                        <?php echo __('Theme', $current_user['language']); ?>
                        <select name="theme">
                            <option value="light" <?php echo ($current_user['theme'] == 'light') ? 'selected' : ''; ?>><?php echo __('Light', $current_user['language']); ?></option>
                            <option value="dark" <?php echo ($current_user['theme'] == 'dark') ? 'selected' : ''; ?>><?php echo __('Dark', $current_user['language']); ?></option>
                        </select>
                    </label>
                    <label for="language">
                        <?php echo __('Language', $current_user['language']); ?>
                        <select name="language">
                            <option value="en" <?php echo ($current_user['language'] == 'en') ? 'selected' : ''; ?>><?php echo __('English', $current_user['language']); ?></option>
                            <option value="ms" <?php echo ($current_user['language'] == 'ms') ? 'selected' : ''; ?>><?php echo __('Malay', $current_user['language']); ?></option>
                            <option value="zh" <?php echo ($current_user['language'] == 'zh') ? 'selected' : ''; ?>><?php echo __('Mandarin', $current_user['language']); ?></option>
                        </select>
                    </label>
                    <button type="submit" name="update_preferences" class="button"><?php echo __('Update Preferences', $current_user['language']); ?></button>
                </form>
            </div>
            <div class="setting-group">
                <h3><i class="fas fa-bell"></i> <?php echo __('Notifications', $current_user['language']); ?>: <?php echo $current_user['notifications_enabled'] ? __('Enabled', $current_user['language']) : __('Disabled', $current_user['language']); ?></h3>
                <form method="POST" style="display: flex; gap: 10px;">
                    <button type="submit" name="update_notifications" value="enable" class="button enable-btn"><?php echo __('Enable', $current_user['language']); ?></button>
                    <button type="submit" name="update_notifications" value="disable" class="button disable-btn"><?php echo __('Disable', $current_user['language']); ?></button>
                </form>
            </div>
        </div>

        <!-- Security -->
        <div class="setting-section">
            <h2><i class="fas fa-lock"></i> <?php echo __('Security', $current_user['language']); ?></h2>
            <div class="setting-group">
                <h3><?php echo __('Two-Factor Authentication', $current_user['language']); ?></h3>
                <form method="POST">
                    <label for="2fa">
                        <?php echo __('Enable 2FA', $current_user['language']); ?>
                        <input type="checkbox" name="2fa" value="1">
                    </label>
                    <button type="submit" name="update_2fa" class="button"><?php echo __('Update 2FA', $current_user['language']); ?></button>
                </form>
            </div>
        </div>

        <!-- Data & Support -->
        <div class="setting-section">
            <h2><i class="fas fa-database"></i> <?php echo __('Data & Support', $current_user['language']); ?></h2>
            <div class="setting-group">
                <h3><?php echo __('Export Your Data', $current_user['language']); ?></h3>
                <form method="POST">
                    <button type="submit" name="export_data" class="button"><?php echo __('Download My Data', $current_user['language']); ?></button>
                </form>
            </div>
            <div class="setting-group">
                <h3><?php echo __('Help & Support', $current_user['language']); ?></h3>
                <p><?php echo __('Need help?', $current_user['language']); ?> <a href="support.php" class="support-link"><?php echo __('Contact Support', $current_user['language']); ?></a> <?php echo __('or visit our', $current_user['language']); ?> <a href="faq.php" class="support-link"><?php echo __('FAQ', $current_user['language']); ?></a>.</p>
            </div>
            <div class="setting-group">
                <h3><?php echo __('Delete Account', $current_user['language']); ?></h3>
                <form method="POST" onsubmit="return confirm('<?php echo __('Are you sure you want to delete your account? This action cannot be undone.', $current_user['language']); ?>')">
                    <button type="submit" name="delete_account" class="button delete-btn"><?php echo __('Delete Account', $current_user['language']); ?></button>
                </form>
            </div>
        </div>

    </div>
</div>

</body>
</html>
