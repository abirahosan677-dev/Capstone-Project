<?php
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$conn = getDBConnection();
$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'] ?? 'User';

// Fetch user's theme preference
$stmt = $conn->prepare("SELECT theme FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$theme = $user['theme'] ?? 'light';

// Sample educational materials data (could be fetched from DB or API)
$educational_materials = [
    [
        'subject' => 'Mathematics',
        'title' => 'Algebra Basics',
        'description' => 'Introduction to algebraic concepts and equations.',
        'image' => 'https://cdn-icons-png.flaticon.com/512/1995/1995574.png',
        'link' => 'https://www.khanacademy.org/math/algebra'
    ],
    [
        'subject' => 'Science',
        'title' => 'Physics Fundamentals',
        'description' => 'Learn the basics of physics including motion and forces.',
        'image' => 'https://cdn-icons-png.flaticon.com/512/1995/1995557.png',
        'link' => 'https://www.khanacademy.org/science/physics'
    ],
    [
        'subject' => 'History',
        'title' => 'World History Overview',
        'description' => 'Explore major events in world history.',
        'image' => 'https://cdn-icons-png.flaticon.com/512/1995/1995550.png',
        'link' => 'https://www.history.com/topics'
    ],
    [
        'subject' => 'Computer Science',
        'title' => 'Introduction to Programming',
        'description' => 'Start coding with beginner-friendly tutorials.',
        'image' => 'https://cdn-icons-png.flaticon.com/512/1995/1995553.png',
        'link' => 'https://www.codecademy.com/learn/learn-how-to-code'
    ],
    [
        'subject' => 'English Literature',
        'title' => 'Classic Literature Analysis',
        'description' => 'Study famous works by Shakespeare, Dickens, and other literary giants.',
        'image' => 'https://cdn-icons-png.flaticon.com/512/1995/1995548.png',
        'link' => 'https://www.sparknotes.com/lit/'
    ],
    [
        'subject' => 'Geography',
        'title' => 'World Geography & Cultures',
        'description' => 'Discover different countries, cultures, and geographical features around the world.',
        'image' => 'https://cdn-icons-png.flaticon.com/512/1995/1995549.png',
        'link' => 'https://www.nationalgeographic.com/'
    ]
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Educational Materials - Vibely</title>
<link rel="stylesheet" href="vibely.css" />
<style>
body {
    font-family: Arial, sans-serif;
    margin: 0;
    background: #f5f6fa;
}
.container {
    max-width: 900px;
    margin: 40px auto;
    padding: 20px;
    background: white;
    border-radius: 10px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}
h1 {
    text-align: center;
    margin-bottom: 30px;
    color: #4a90e2;
}
.materials-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit,minmax(220px,1fr));
    gap: 20px;
}
.material-card {
    background: #fff;
    border-radius: 8px;
    box-shadow: 0 1px 4px rgba(0,0,0,0.1);
    padding: 15px;
    text-align: center;
    transition: box-shadow 0.3s ease;
    display: flex;
    flex-direction: column;
    height: 100%;
}
.material-card:hover {
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}
.material-image {
    width: 80px;
    height: 80px;
    object-fit: contain;
    margin: 0 auto 15px;
    display: block;
}
.material-title {
    font-size: 1.1rem;
    font-weight: bold;
    margin-bottom: 8px;
    color: #333;
}
.material-subject {
    font-size: 0.9rem;
    color: #777;
    margin-bottom: 12px;
}
.material-description {
    font-size: 0.9rem;
    color: #555;
    margin-bottom: auto;
    flex-grow: 1;
}
.material-link {
    display: inline-block;
    padding: 8px 15px;
    background: #4a90e2;
    color: white;
    border-radius: 5px;
    text-decoration: none;
    font-weight: bold;
    transition: background 0.3s ease;
    margin-top: 15px;
}
.material-link:hover {
    background: #357ABD;
}
.back-home {
    display: block;
    width: fit-content;
    margin: 30px auto 0;
    padding: 10px 20px;
    background: #4a90e2;
    color: white;
    border-radius: 6px;
    text-align: center;
    text-decoration: none;
    font-weight: bold;
    transition: background 0.3s ease;
}
.back-home:hover {
    background: #357ABD;
}
</style>
</head>
<body class="<?php echo $theme; ?>">
<div class="container">
    <h1>Educational Materials</h1>
    <div class="materials-grid">
        <?php foreach ($educational_materials as $material): ?>
            <div class="material-card">
                <img src="<?php echo htmlspecialchars($material['image']); ?>" alt="<?php echo htmlspecialchars($material['title']); ?>" class="material-image" />
                <div class="material-subject"><?php echo htmlspecialchars($material['subject']); ?></div>
                <div class="material-title"><?php echo htmlspecialchars($material['title']); ?></div>
                <div class="material-description"><?php echo htmlspecialchars($material['description']); ?></div>
                <a href="<?php echo htmlspecialchars($material['link']); ?>" target="_blank" class="material-link">View Content</a>
            </div>
        <?php endforeach; ?>
    </div>
    <a href="homepage.php" class="back-home">Back to Homepage</a>
</div>
</body>
</html>
