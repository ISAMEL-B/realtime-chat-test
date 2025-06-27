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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chat with <?= htmlspecialchars($contact['name']) ?> | Bisure</title>
    <link rel="stylesheet" href="css/base.css">
    <link rel="stylesheet" href="css/converse.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
/* Additional styles for chat interface */
.online-badge {
    position: absolute;
    bottom: 0;
    right: 0;
    width: 12px;
    height: 12px;
    background-color: #4CAF50;
    border: 2px solid #fff;
    border-radius: 50%;
}

.contact-avatar {
    position: relative;
}

.typing-indicator {
    padding: 5px 15px;
    font-size: 12px;
    color: #666;
    font-style: italic;
}

.message-status {
    margin-left: 5px;
    color: #666;
}

.message-status .fa-check-double {
    color: #4CAF50;
}
</style>
</head>
<body>
<div class="app-container">
    <!-- Main container for the entire chat application -->

    <div class="chat-container">
        <!-- Container specifically for the chat conversation and controls -->

        <header class="chat-header">
            <!-- Top header bar showing contact info and typing status -->

            <div class="contact-info">
                <!-- Section containing contact avatar, name, and status -->

                <a href="contacts.php" class="back-button"><i class="fas fa-arrow-left"></i></a>
                <!-- Back button icon linking back to contacts list -->

                <div class="contact-avatar" style="background-image: url('<?= !empty($contact['profile_pic']) ? htmlspecialchars($contact['profile_pic']) : 'assets/default-profile.png' ?>')">
                    <!-- Contact avatar image, uses default if no profile picture -->

                    <?php if ($contact['is_online']): ?>
                        <span class="online-badge"></span>
                        <!-- Small green badge indicating contact is online -->
                    <?php endif; ?>
                </div>

                <div class="contact-details">
                    <!-- Contact's name and online/offline status -->

                    <span class="contact-name"><?= htmlspecialchars($contact['name']) ?></span>
                    <!-- Display the contact’s name safely -->

                    <span class="contact-status" id="contact-status">
                        <?= $contact['is_online'] ? 'online' : 'last seen ' . date("H:i", strtotime($contact['last_seen'])) ?>
                        <!-- Show "online" if user is online, else last seen time -->
                    </span>
                </div>
            </div>

            <div class="typing-indicator" id="typing-indicator" style="display: none;">
                <span><?= htmlspecialchars($contact['name']) ?> is typing...</span>
                <!-- Hidden by default, shown via JS when contact is typing -->
            </div>
        </header>

        <div class="messages-container" id="messages-container">
            <!-- Container for chat message bubbles -->

            <?php foreach ($messages as $message): ?>
                <div class="message <?= $message['sender_id'] == $user_id ? 'sent' : 'received' ?>">
                    <!-- Message bubble: 'sent' if by current user, else 'received' -->

                    <div class="message-content">
                        <!-- Actual message content -->

                        <p><?= htmlspecialchars($message['message']) ?></p>
                        <!-- Display message text safely -->

                        <span class="message-time">
                            <?= date("H:i", strtotime($message['created_at'])) ?>
                            <!-- Show time message was sent -->

                            <?php if ($message['sender_id'] == $user_id): ?>
                                <span class="message-status">
                                    <?= $message['is_read'] ? '<i class="fas fa-check-double"></i>' : '<i class="fas fa-check"></i>' ?>
                                    <!-- Show single check if sent, double check if read -->
                                </span>
                            <?php endif; ?>
                        </span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="message-input">
            <!-- Input area to type and send new messages -->

            <button class="emoji-button" title="Emoji"><i class="far fa-smile"></i></button>
            <!-- Button to open emoji picker (functionality can be added) -->

            <input type="text" id="message-input" placeholder="Type a message">
            <!-- Text input box for typing message -->

            <button class="send-button" id="send-button"><i class="fas fa-paper-plane"></i></button>
            <!-- Send button with paper plane icon -->
        </div>
    </div>
</div>

<script>
// Constants and variables

const userId = <?= $user_id ?>; // Current logged-in user's ID from PHP
const contactId = <?= $contact_id ?>; // The contact's user ID you are chatting with
const messagesContainer = document.getElementById('messages-container'); // Container DOM element for chat messages
const messageInput = document.getElementById('message-input'); // Input field for typing messages
const sendButton = document.getElementById('send-button'); // Button to send message
const typingIndicator = document.getElementById('typing-indicator'); // DOM element that shows "typing..." indicator
const contactStatus = document.getElementById('contact-status'); // Element displaying contact's online/offline status

let socket; // WebSocket connection variable
let typingTimer; // Timer used to track typing timeout
const typingDelay = 1000; // Delay (in ms) after which typing stops (1 second)

// Initialize WebSocket connection to server for real-time communication
function initWebSocket() {
    // Determine protocol: use 'wss://' if HTTPS, else 'ws://'
    const protocol = window.location.protocol === 'https:' ? 'wss://' : 'ws://';
    const host = window.location.host; // Current host (domain + port)
    
    // Create new WebSocket connection with user ID as query parameter
    socket = new WebSocket(`${protocol}${host}:8080?user_id=${userId}`);

    // Event: Connection opened
    socket.onopen = () => {
        console.log('WebSocket connection established');
    };

    // Event: Message received from WebSocket server
    socket.onmessage = (event) => {
        const data = JSON.parse(event.data); // Parse incoming JSON message
        
        // Handle based on message type
        switch(data.type) {
            case 'message':
                handleIncomingMessage(data); // Process new incoming chat message
                break;
                
            case 'typing':
                handleTypingNotification(data); // Show/hide typing indicator
                break;
                
            case 'presence':
                handlePresenceUpdate(data); // Update online/offline status of contact
                break;
                
            case 'read_receipt':
                handleReadReceipt(data); // Update message read status UI
                break;
        }
    };

    // Event: Error with WebSocket
    socket.onerror = (error) => {
        console.error('WebSocket error:', error);
    };

    // Event: WebSocket closed, try to reconnect after 3 seconds
    socket.onclose = () => {
        console.log('WebSocket connection closed. Reconnecting...');
        setTimeout(initWebSocket, 3000);
    };
}

// Handle receiving a new message from the contact
function handleIncomingMessage(data) {
    // Only show message if it's from the current contact in chat
    if (data.from === contactId) {
        // Append received message to chat window
        appendMessage({
            message: data.content,
            time: data.time || new Date().toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'}),
            isRead: false
        }, false); // false = message is received, not sent by user
        
        // Send a read receipt back to the server if WebSocket is open
        if (socket.readyState === WebSocket.OPEN) {
            socket.send(JSON.stringify({
                type: 'read_receipt',
                message_id: data.message_id,
                reader_id: userId
            }));
        }
    }
}

// Show or hide typing indicator based on contact's typing status
function handleTypingNotification(data) {
    // Only act if typing notification is from current contact
    if (data.from === contactId) {
        if (data.is_typing) {
            typingIndicator.style.display = 'block'; // Show "typing..." indicator
        } else {
            typingIndicator.style.display = 'none'; // Hide indicator
        }
    }
}

// Update contact's online/offline status display
function handlePresenceUpdate(data) {
    // Only update if the update is about the current contact
    if (data.user_id === contactId) {
        // Show 'online' or 'last seen' with timestamp
        contactStatus.textContent = data.status === 'online' ? 'online' : 
            `last seen ${new Date().toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'})}`;
    }
}

// Update UI to indicate messages were read by the contact
function handleReadReceipt(data) {
    if (data.read_by === contactId) {
        // Find all sent messages and update their status icons to "read" (double check marks)
        const messages = document.querySelectorAll('.message.sent .message-status');
        messages.forEach(msg => {
            msg.innerHTML = '<i class="fas fa-check-double"></i>'; // FontAwesome double check icon
        });
    }
}

// Add a message to the chat window, indicating if it's sent by user or received
function appendMessage(msg, isOwn = false) {
    const div = document.createElement('div'); // Create a container div
    div.className = `message ${isOwn ? 'sent' : 'received'}`; // Add CSS classes accordingly
    
    // Set inner HTML with message content and time; add read status icon if own message
    div.innerHTML = `
        <div class="message-content">
            <p>${msg.message}</p>
            <span class="message-time">
                ${msg.time}
                ${isOwn ? `<span class="message-status">
                    <i class="fas fa-${msg.isRead ? 'check-double' : 'check'}"></i>
                </span>` : ''}
            </span>
        </div>
    `;
    
    messagesContainer.appendChild(div); // Add the new message div to container
    scrollToBottom(); // Scroll chat to show latest message
}

// Send a message to the server when user clicks send or presses Enter
async function sendMessage() {
    const message = messageInput.value.trim(); // Get trimmed input value
    if (!message) return; // Do nothing if message is empty

    try {
        // Send POST request to backend 'send_message.php' with receiver ID and message JSON
        const response = await fetch('send_message.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                receiver_id: contactId,
                message: message
            })
        });
        
        const data = await response.json(); // Parse response JSON
        
        if (data.success) {
            // Append own sent message to chat UI
            appendMessage({
                message: data.message.content,
                time: data.message.time,
                isRead: false
            }, true); // true = this is own sent message
            
            // Notify WebSocket server with the message for real-time delivery
            if (socket.readyState === WebSocket.OPEN) {
                socket.send(JSON.stringify({
                    type: 'message',
                    from: userId,
                    to: contactId,
                    content: message,
                    time: data.message.time
                }));
            }
            
            messageInput.value = ''; // Clear input box after sending
        }
    } catch (error) {
        console.error('Error sending message:', error);
    }
}

// Send typing status notifications while user types
function sendTypingNotification(isTyping) {
    if (socket.readyState === WebSocket.OPEN) {
        socket.send(JSON.stringify({
            type: 'typing',
            from: userId,
            to: contactId,
            is_typing: isTyping
        }));
    }
}

// Scroll messages container to bottom to show latest message
function scrollToBottom() {
    messagesContainer.scrollTop = messagesContainer.scrollHeight;
}

// DOM ready event to initialize everything once page loads
document.addEventListener('DOMContentLoaded', () => {
    initWebSocket(); // Open WebSocket connection for real-time comm
    scrollToBottom(); // Scroll chat to bottom

    // Send message on clicking send button
    sendButton.addEventListener('click', sendMessage);

    // Send message on pressing Enter key in input field
    messageInput.addEventListener('keypress', (e) => {
        if (e.key === 'Enter') sendMessage();
    });

    // Detect typing input events and notify contact with delay to avoid flooding
    messageInput.addEventListener('input', () => {
        clearTimeout(typingTimer); // Clear previous timer if any
        sendTypingNotification(true); // Notify "typing started"
        typingTimer = setTimeout(() => {
            sendTypingNotification(false); // Notify "typing stopped" after delay
        }, typingDelay);
    });
});

// Close WebSocket connection when page unloads to clean up resources
window.addEventListener('beforeunload', () => {
    if (socket && socket.readyState === WebSocket.OPEN) {
        socket.close();
    }
});
</script>

</body>
</html>