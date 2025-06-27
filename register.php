<?php
require 'db.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $phone = $_POST['phone'];
    $name = $_POST['name'];
    $password = password_hash($_POST['password'], PASSWORD_BCRYPT);
    
    try {
        $stmt = $db->prepare("INSERT INTO users (phone, name, password) VALUES (?, ?, ?)");
        $stmt->execute([$phone, $name, $password]);
        
        $_SESSION['user_id'] = $db->lastInsertId();
        $_SESSION['phone'] = $phone;
        $_SESSION['name'] = $name;
        
        header("Location: contacts.php");
        exit();
    } catch(PDOException $e) {
        $error = "Phone number already exists or registration failed.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bisure - Register</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="auth-container">
        <h1>Create your Bisure account</h1>
        <?php if (isset($error)): ?>
            <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <form method="POST">
            <input type="text" name="name" placeholder="Your name" required>
            <input type="tel" name="phone" placeholder="Phone number" required>
            <input type="password" name="password" placeholder="Password" required>
            <button type="submit">Register</button>
        </form>
        <p>Already have an account? <a href="login.php">Login</a></p>
    </div>
</body>
</html>