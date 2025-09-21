<?php
// reportsuccess.php
session_start();
$username = $_SESSION['username'] ?? 'User';
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Report Submitted - Vibely</title>
<link rel="stylesheet" href="vibely.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
<style>
body { 
    font-family: Arial, sans-serif; 
    margin:0; 
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    display:flex;
    justify-content:center;
    align-items:center;
    min-height:100vh;
}
.container { 
    background: #fff; 
    padding: 30px; 
    border-radius: 10px; 
    box-shadow: 0 4px 8px rgba(0,0,0,0.2); 
    text-align:center; 
    max-width:400px; 
}
h1 { color:#4a90e2; margin-bottom:20px; }
p { color:#333; margin-bottom:30px; }
a { text-decoration:none; color:white; background:#4a90e2; padding:10px 15px; border-radius:5px; }
a:hover { background:#3a78c2; }
</style>
</head>
<body>
<div class="container">
    <h1>Report Submitted!</h1>
    <p>Thank you, <?= htmlspecialchars($username) ?>. Your report has been successfully sent.</p>
    <a href="content.php"><i class="fas fa-arrow-left"></i> Back to Posts</a>
</div>
</body>
</html>