<?php
require 'db.php';
session_start();

// Authentication check
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Contact parameter validation
if (!isset($_GET['contact']) || !ctype_digit($_GET['contact'])) {
    header("Location: contacts.php");
    exit();
}

$contact_id = (int)$_GET['contact'];
$user_id = (int)$_SESSION['user_id'];

// Get contact info with online status
$stmt = $db->prepare("SELECT id, name, phone, profile_pic, last_seen, is_online FROM users WHERE id = ?");
$stmt->execute([$contact_id]);
$contact = $stmt->fetch();

if (!$contact) {
    header("Location: contacts.php");
    exit();
}

// Get messages between user and contact
$stmt = $db->prepare("
    SELECT m.*, u.name as sender_name 
    FROM messages m 
    JOIN users u ON m.sender_id = u.id 
    WHERE (m.sender_id = ? AND m.receiver_id = ?) 
       OR (m.sender_id = ? AND m.receiver_id = ?) 
    ORDER BY m.created_at ASC
");
$stmt->execute([$user_id, $contact_id, $contact_id, $user_id]);
$messages = $stmt->fetchAll();

// Mark messages as read
$db->prepare("UPDATE messages SET is_read = TRUE WHERE receiver_id = ? AND sender_id = ? AND is_read = FALSE")
   ->execute([$user_id, $contact_id]);

// Update user's last seen
$db->prepare("UPDATE users SET last_seen = NOW(), is_online = TRUE WHERE id = ?")
   ->execute([$user_id]);

function notifyMessageRead($reader_id, $sender_id, $message_ids) {
    $socketData = [
        'type' => 'read_receipt',
        'reader_id' => $reader_id,
        'sender_id' => $sender_id,
        'message_ids' => $message_ids,
        'timestamp' => time()
    ];

    sendToWebSocket($socketData);
}

function notifyMultipleMessagesRead($reader_id, $messages) {
    $grouped = [];
    foreach ($messages as $msg) {
        $sender_id = $msg['sender_id'];
        if (!isset($grouped[$sender_id])) {
            $grouped[$sender_id] = [];
        }
        $grouped[$sender_id][] = $msg['id'];
    }

    foreach ($grouped as $sender_id => $message_ids) {
        notifyMessageRead($reader_id, $sender_id, $message_ids);
    }
}

function sendToWebSocket($data) {
    $context = stream_context_create();
    $fp = @stream_socket_client(
        "tcp://127.0.0.1:8080",
        $errno,
        $errstr,
        1,
        STREAM_CLIENT_CONNECT | STREAM_CLIENT_PERSISTENT,
        $context
    );

    if ($fp) {
        fwrite($fp, json_encode($data) . "\n");
        fclose($fp);
    } else {
        error_log("WebSocket notification failed: $errstr ($errno)");
        queueWebSocketNotification($data);
    }
}

function queueWebSocketNotification($data) {
    try {
        $db = new PDO("mysql:host=localhost;dbname=bisurechat", "root", "");
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $stmt = $db->prepare("INSERT INTO pending_ws_notifications (notification_data, created_at, attempts) VALUES (?, NOW(), 1)");
        $stmt->execute([json_encode($data)]);
    } catch (PDOException $e) {
        error_log("Failed to queue WebSocket notification: " . $e->getMessage());
    }
}
