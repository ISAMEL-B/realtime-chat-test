<?php
require 'db.php';
// Include the database connection script to interact with the database

session_start();
// Start the PHP session to access session variables (e.g., user_id)

header('Content-Type: application/json');
// Set HTTP response header to return JSON content to the client

// Check if user is logged in by verifying if 'user_id' exists in session
if (!isset($_SESSION['user_id'])) {
    http_response_code(401); // Unauthorized HTTP status code
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit(); // Stop further execution since user is not logged in
}

// Ensure the request method is POST to accept message sending only via POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Method Not Allowed HTTP status code
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit(); // Stop execution if request method is not POST
}

// Read raw JSON data sent in the request body
$rawInput = file_get_contents('php://input');

// Decode JSON string into PHP associative array
$payload = json_decode($rawInput, true);

// Validate and extract the receiver's ID; cast to integer for safety
$receiver_id = isset($payload['receiver_id']) ? (int)$payload['receiver_id'] : 0;

// Extract and trim the message content to remove extra whitespace
$message = isset($payload['message']) ? trim($payload['message']) : '';

// Validate receiver_id: must be a positive integer
if ($receiver_id <= 0) {
    http_response_code(400); // Bad Request HTTP status code
    echo json_encode(['success' => false, 'error' => 'Invalid recipient']);
    exit(); // Stop execution for invalid recipient
}

// Validate message content: must not be empty
if ($message === '') {
    http_response_code(400); // Bad Request HTTP status code
    echo json_encode(['success' => false, 'error' => 'Message cannot be empty']);
    exit(); // Stop execution if message is empty
}

try {
    // Begin a transaction to ensure atomic database operations
    $db->beginTransaction();

    // Insert the new message into the messages table
    $stmt = $db->prepare("INSERT INTO messages (sender_id, receiver_id, message) VALUES (?, ?, ?)");
    $stmt->execute([$_SESSION['user_id'], $receiver_id, $message]);

    // Get the ID of the newly inserted message for reference
    $message_id = $db->lastInsertId();

    // Check if the contact relationship exists from sender to receiver
    $stmt = $db->prepare("SELECT 1 FROM contacts WHERE user_id = ? AND contact_id = ?");
    $stmt->execute([$_SESSION['user_id'], $receiver_id]);

    // If no contact record exists, insert the contact relationship both ways (sender <-> receiver)
    if (!$stmt->fetch()) {
        $insertContact = $db->prepare("INSERT INTO contacts (user_id, contact_id) VALUES (?, ?), (?, ?)");
        $insertContact->execute([$_SESSION['user_id'], $receiver_id, $receiver_id, $_SESSION['user_id']]);
    }

    // Update last_seen timestamp and set is_online to TRUE for both sender and receiver
    $db->prepare("UPDATE users SET last_seen = NOW(), is_online = TRUE WHERE id IN (?, ?)")
       ->execute([$_SESSION['user_id'], $receiver_id]);

    // Retrieve the inserted message along with sender's user info (name, avatar, online status)
    $stmt = $db->prepare("
        SELECT 
            m.id, m.message, m.created_at, m.is_read, 
            u.name AS sender_name, u.profile_pic AS sender_avatar,
            u.is_online AS sender_online
        FROM messages m
        JOIN users u ON m.sender_id = u.id
        WHERE m.id = ?
    ");
    $stmt->execute([$message_id]);

    // Fetch the message data as associative array
    $message_data = $stmt->fetch(PDO::FETCH_ASSOC);

    // Commit the transaction to finalize DB changes
    $db->commit();

    // Prepare response array with message and sender details to return as JSON
    $response = [
        'success' => true,
        'message' => [
            'id' => $message_data['id'],
            'content' => $message_data['message'],
            'time' => date("H:i", strtotime($message_data['created_at'])), // Format time
            'date' => date("M j", strtotime($message_data['created_at'])), // Format date
            'is_read' => (bool)$message_data['is_read'], // Boolean read status
            'sender' => [
                'id' => $_SESSION['user_id'],
                'name' => $message_data['sender_name'],
                'avatar' => $message_data['sender_avatar'] ?: 'assets/default-profile.png',
                'online' => (bool)$message_data['sender_online']
            ]
        ],
        'metadata' => [
            'sender_id' => $_SESSION['user_id'],
            'receiver_id' => $receiver_id,
            'timestamp' => time() // Current Unix timestamp
        ]
    ];

    // Prepare data to send to WebSocket server for real-time notification
    $socketData = [
        'type' => 'message',
        'from' => $_SESSION['user_id'],
        'to' => $receiver_id,
        'content' => $message_data['message'],
        'message_id' => $message_data['id'],
        'time' => $response['message']['time']
    ];

    // Create a stream context for socket connection (default here)
    $context = stream_context_create();

    // Open a persistent TCP socket connection to local WebSocket server on port 8080
    $fp = @stream_socket_client(
        "tcp://127.0.0.1:8080",
        $errno, $errstr, 1,
        STREAM_CLIENT_CONNECT | STREAM_CLIENT_PERSISTENT,
        $context
    );

    if ($fp) {
        // Send the JSON-encoded message data over the TCP socket followed by newline
        fwrite($fp, json_encode($socketData) . "\n");
        fclose($fp); // Close the socket connection
    } else {
        // If socket connection failed, log the error message
        error_log("WebSocket send failed: $errstr ($errno)");

        // Optional fallback: Save the message for later retry by inserting into a pending table
        $db->prepare("
            INSERT INTO pending_ws_messages (message_data, created_at, attempts)
            VALUES (?, NOW(), 1)
        ")->execute([json_encode($socketData)]);
    }

    // Send the JSON response back to the client
    echo json_encode($response);

} catch (PDOException $e) {
    // On any database error, rollback transaction to maintain integrity
    $db->rollBack();

    http_response_code(500); // Internal Server Error HTTP status code

    // Send error details back as JSON (avoid in production for security)
    echo json_encode([
        'success' => false,
        'error' => 'Database error occurred.',
        'debug' => $e->getMessage()
    ]);

    // Log the error message in server logs for troubleshooting
    error_log("send_message.php error: " . $e->getMessage());
}
