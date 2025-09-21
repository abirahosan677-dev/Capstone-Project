<?php
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$conn = getDBConnection();

// Fetch user's theme preference
$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT theme FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$theme = $user['theme'] ?? 'light';

// Create community_messages table if not exists
$conn->query("
    CREATE TABLE IF NOT EXISTS community_messages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        community_id INT NOT NULL,
        user_id INT NOT NULL,
        message TEXT NOT NULL,
        message_type ENUM('text','image','file') DEFAULT 'text',
        file_url VARCHAR(255),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (community_id) REFERENCES communities(id),
        FOREIGN KEY (user_id) REFERENCES users(id)
    )
");

// Create private_messages table if not exists
$conn->query("
    CREATE TABLE IF NOT EXISTS private_messages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        sender_id INT NOT NULL,
        receiver_id INT NOT NULL,
        message TEXT NOT NULL,
        message_type ENUM('text','image','file') DEFAULT 'text',
        file_url VARCHAR(255),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (sender_id) REFERENCES users(id),
        FOREIGN KEY (receiver_id) REFERENCES users(id)
    )
");

// Fetch users for contact list
$users_result = $conn->query("SELECT username FROM users");
$users = [];
while ($row = $users_result->fetch_assoc()) {
    if (strtolower($row['username']) != 'vibely') { // skip vibely from DB
        $users[] = $row['username'];
    }
}

// Selected contact
$selected_contact = $_GET['contact'] ?? 'Vibely';

// Handle message submission (cannot send to Vibely)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['message'])) {
    $message = trim($_POST['message']);

    if ($message !== '') {
        // If a receiver username is given → PRIVATE MESSAGE
        if (isset($_POST['receiver']) && $_POST['receiver'] !== '') {
            $receiver = trim($_POST['receiver']);

            // Prevent sending to "vibely" if needed
            if (strtolower($receiver) != 'vibely') {
                // Find receiver_id from users table
                $stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
                $stmt->bind_param("s", $receiver);
                $stmt->execute();
                $result = $stmt->get_result();

                if ($row = $result->fetch_assoc()) {
                    $receiver_id = $row['id'];

                    // Insert private message
                    $stmt = $conn->prepare("INSERT INTO private_messages (sender_id, receiver_id, message, created_at) VALUES (?, ?, ?, NOW())");
                    $stmt->bind_param("iis", $user_id, $receiver_id, $message);
                    $stmt->execute();
                }
            }

        // Else if a community_id is given → COMMUNITY MESSAGE
        } elseif (isset($_POST['community_id']) && is_numeric($_POST['community_id'])) {
            $community_id = (int) $_POST['community_id'];

            // Insert community message
            $stmt = $conn->prepare("INSERT INTO community_messages (community_id, user_id, message, created_at) VALUES (?, ?, ?, NOW())");
            $stmt->bind_param("iis", $community_id, $user_id, $message);
            $stmt->execute();
        }
    }
}

// Fetch messages based on selected contact
$messages = [];

// Check if selected contact is a community (starts with #) or private user
if (strpos($selected_contact, '#') === 0) {
    // Community message - extract community ID from contact name
    $community_name = substr($selected_contact, 1); // Remove # prefix

    // Get community ID from name (assuming communities table exists)
    $stmt = $conn->prepare("SELECT id FROM communities WHERE name = ?");
    $stmt->bind_param("s", $community_name);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        $community_id = $row['id'];

        // Fetch community messages
        $stmt = $conn->prepare("
            SELECT m.*, u.username as sender_username
            FROM community_messages m
            JOIN users u ON m.user_id = u.id
            WHERE m.community_id=?
            ORDER BY m.created_at ASC
        ");
        $stmt->bind_param("i", $community_id);
        $stmt->execute();
        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            $messages[] = $row;
        }
    }
} elseif ($selected_contact !== 'Vibely') {
    // Private message - get receiver ID
    $stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->bind_param("s", $selected_contact);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        $receiver_id = $row['id'];

        // Fetch private messages between current user and selected contact
        $stmt = $conn->prepare("
            SELECT m.*, u.username as sender_username
            FROM private_messages m
            JOIN users u ON m.sender_id = u.id
            WHERE (m.sender_id=? AND m.receiver_id=?)
               OR (m.sender_id=? AND m.receiver_id=?)
            ORDER BY m.created_at ASC
        ");
        $stmt->bind_param("iiii", $user_id, $receiver_id, $receiver_id, $user_id);
        $stmt->execute();
        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            $messages[] = $row;
        }
    }
}
?>

<!DOCTYPE html>
<div class="auth-container">
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Messaging - Vibely</title>
<link rel="stylesheet" href="vibely.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
<style>
body { margin:0; font-family:Arial,sans-serif; }
.auth-container { display:flex; justify-content:center; padding:50px 0; background:#f5f6fa; min-height:100vh; }
.messaging-wrapper { display:flex; width:90%; max-width:900px; background:#fff; border-radius:10px; box-shadow:0 4px 6px rgba(0,0,0,0.1); overflow:hidden; height:80vh; }

.contact-list { width:220px; border-right:1px solid #ddd; background:#f9f9f9; display:flex; flex-direction:column; }
.contact-list input { padding:10px; margin:10px; border-radius:5px; border:1px solid #ccc; }
.contact { padding:15px; border-bottom:1px solid #ddd; cursor:pointer; font-weight:bold; }
.contact.active { background:#4a90e2; color:white; }

.messaging-container { flex:1; display:flex; flex-direction:column; }
.messaging-header { font-size:1.2rem; font-weight:bold; border-bottom:1px solid #ddd; padding:10px; background:#fff; position:sticky; top:0; z-index:10; }
.messages { flex:1; overflow-y:auto; padding:10px; background:#fafafa; }

.message { margin-bottom:10px; padding:10px; border-radius:8px; max-width:70%; word-wrap:break-word; }
.sent { background-color: #6a4bcf; color:white; margin-left:auto; }
.received { background-color: #e0e0e0; color:black; margin-right:auto; }

.message-form { display:flex; gap:10px; padding:10px; border-top:1px solid #ddd; }
.message-form input[type="text"] { flex:1; padding:10px; border-radius:8px; border:1px solid #ccc; }
.message-form button { padding:10px 15px; border:none; background:#6a4bcf; color:white; border-radius:5px; cursor:pointer; }

.back-btn { display:block; text-align:center; margin:10px auto; padding:8px 12px; background:#4a90e2; color:white; border-radius:5px; text-decoration:none; width:fit-content; }
</style>
</head>
<body class="<?php echo $theme; ?>">
    <div class="messaging-wrapper">

        <!-- Left: Contact list -->
        <div class="contact-list">
            <input type="text" id="contactSearch" placeholder="Search contacts...">
            <div class="contact <?php echo $selected_contact=='Vibely'?'active':''; ?>" onclick="window.location='?contact=Vibely'">Vibely</div>
            <?php foreach ($users as $u): ?>
                <div class="contact <?php echo $selected_contact==$u?'active':''; ?>" onclick="window.location='?contact=<?php echo urlencode($u); ?>'">
                    <?php echo htmlspecialchars($u); ?>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Right: Chat area -->
        <div class="messaging-container">
            <div class="messaging-header"><?php echo htmlspecialchars($selected_contact); ?></div>

            <div class="messages">
    <?php if (!empty($messages)): ?>
        <?php foreach ($messages as $msg): ?>
            <div class="message <?php echo (
                (isset($msg['sender_id']) && $msg['sender_id'] == $user_id) || 
                (isset($msg['user_id']) && $msg['user_id'] == $user_id)
            ) ? 'sent' : 'received'; ?>">
                
                <strong>
                    <?php echo (
                        (isset($msg['sender_id']) && $msg['sender_id'] == $user_id) || 
                        (isset($msg['user_id']) && $msg['user_id'] == $user_id)
                    ) ? 'You' : htmlspecialchars($msg['sender_username']); ?>
                </strong>:
                
                <?php echo htmlspecialchars($msg['message']); ?>
                
                <div style="font-size:0.7rem;color:#555;">
                    <?php echo $msg['created_at']; ?>
                </div>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <p>No messages yet.</p>
    <?php endif; ?>
</div>


            <?php if(strtolower($selected_contact) != 'vibely'): ?>
            <form class="message-form" method="POST">
                <input type="hidden" name="receiver" value="<?php echo htmlspecialchars($selected_contact); ?>">
                <input type="text" name="message" placeholder="Type your message..." required>
                <button type="submit"><i class="fas fa-paper-plane"></i> Send</button>
            </form>
            <?php else: ?>
                <div style="text-align:center; padding:10px; color:#888;">Cannot send messages to Vibely</div>
            <?php endif; ?>

            <a href="homepage.php" class="back-btn"><i class="fas fa-arrow-left"></i> Back to Homepage</a>
        </div>

    </div>
</div>

<script>
document.getElementById('contactSearch').addEventListener('keyup', function() {
    const filter = this.value.toLowerCase();
    const contacts = document.querySelectorAll('.contact-list .contact');
    contacts.forEach(contact => {
        contact.style.display = contact.textContent.toLowerCase().includes(filter) ? '' : 'none';
    });
});
</script>
</body>
</html>