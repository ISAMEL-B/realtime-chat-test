<?php
require 'db.php';
session_start();

// if (isset($_SESSION['user_id'])) {
//     header("Location: contacts.php");
//     exit();
// }

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $phone = $_POST['phone'];
    $password = $_POST['password'];
    
    $stmt = $db->prepare("SELECT id, phone, name, password FROM users WHERE phone = ?");
    $stmt->execute([$phone]);
    $user = $stmt->fetch();
    
    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['phone'] = $user['phone'];
        $_SESSION['name'] = $user['name'];
        
        // Update last seen
        $db->prepare("UPDATE users SET last_seen = NOW() WHERE id = ?")->execute([$user['id']]);
        
        header("Location: contacts.php");
        exit();
    } else {
        $error = "Invalid phone number or password.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bisure - Login</title>
    <link rel="stylesheet" href="css/base.css">
<link rel="stylesheet" href="css/auth.css">
</head>
<body>
    <div class="auth-container">
        <h1>Welcome to Bisure</h1>
        <?php if (isset($error)): ?>
            <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <form method="POST">
            <input type="tel" name="phone" placeholder="Phone number" required>
            <input type="password" name="password" placeholder="Password" required>
            <button type="submit">Login</button>
        </form>
        <p>Don't have an account? <a href="register.php">Register</a></p>
    </div>
</body>
</html>