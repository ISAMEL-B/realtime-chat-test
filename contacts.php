<?php
require 'db.php';
session_start();

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$userId = $_SESSION['user_id'];

// Fetch contacts with last message, unread count, online status
$stmt = $db->prepare("
    SELECT 
        u.id, u.name, u.phone, u.profile_pic, u.last_seen, 
        lm.message AS last_message, lm.created_at AS last_message_time,
        COALESCE(uc.unread_count, 0) AS unread_count,
        u.is_online
    FROM contacts c
    JOIN users u ON c.contact_id = u.id
    LEFT JOIN (
        SELECT m1.*
        FROM messages m1
        JOIN (
            SELECT
                CASE
                    WHEN sender_id = ? THEN receiver_id
                    ELSE sender_id
                END AS contact_id,
                MAX(created_at) AS max_created
            FROM messages
            WHERE sender_id = ? OR receiver_id = ?
            GROUP BY contact_id
        ) lm2 ON (
            ((m1.sender_id = ? AND m1.receiver_id = lm2.contact_id) OR
            (m1.receiver_id = ? AND m1.sender_id = lm2.contact_id))
            AND m1.created_at = lm2.max_created
        )
    ) lm ON lm.sender_id = u.id OR lm.receiver_id = u.id
    LEFT JOIN (
        SELECT sender_id AS contact_id, COUNT(*) AS unread_count
        FROM messages
        WHERE receiver_id = ? AND is_read = FALSE
        GROUP BY sender_id
    ) uc ON uc.contact_id = u.id
    WHERE c.user_id = ?
    ORDER BY COALESCE(lm.created_at, '1970-01-01') DESC, u.name ASC
");
$stmt->execute([$userId, $userId, $userId, $userId, $userId, $userId, $userId]);
$contacts = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Update current user online status and last seen
$db->prepare("UPDATE users SET last_seen = NOW(), is_online = TRUE WHERE id = ?")->execute([$userId]);

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <title>Bisure - Contacts</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover" />
    <link rel="stylesheet" href="css/base.css" />
    <link rel="stylesheet" href="css/contacts.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" />
</head>

<body>
    <div class="app-container">
        <div class="sidebar">
            <header class="sidebar-header">
                <div class="user-info">
                    <div class="profile-pic" style="background-image: url('<?= !empty($_SESSION['profile_pic']) ? htmlspecialchars($_SESSION['profile_pic']) : 'assets/default-profile.png' ?>')"></div>
                    <span><?= htmlspecialchars($_SESSION['name']) ?></span>
                </div>
                <div class="sidebar-actions">
                    <button class="icon-button"><i class="fas fa-circle-notch" title="Status"></i></button>
                    <button class="icon-button"><i class="fas fa-comment-alt" title="New chat"></i></button>
                    <button class="icon-button"><i class="fas fa-ellipsis-v" title="Menu"></i></button>
                </div>
            </header>

            <div class="search-bar">
                <input type="text" placeholder="Search or start new chat" />
            </div>

            <div class="contacts-list">
                <?php if (empty($contacts)) : ?>
                    <div class="empty-state">
                        <div class="empty-state-icon"><i class="far fa-comment-dots"></i></div>
                        <h2>Your Conversations</h2>
                        <p>Start new conversations by selecting a contact</p>
                    </div>
                <?php else : ?>
                    <?php foreach ($contacts as $contact) : ?>
                        <a href="converse.php?contact=<?= $contact['id'] ?>" class="contact-item" data-contact-id="<?= $contact['id'] ?>">
                            <div class="contact-avatar" style="background-image: url('<?= !empty($contact['profile_pic']) ? htmlspecialchars($contact['profile_pic']) : 'assets/default-profile.png' ?>')">
                                <?php if ($contact['is_online']) : ?>
                                    <span class="online-badge"></span>
                                <?php endif; ?>
                            </div>
                            <div class="contact-details">
                                <div class="contact-name-time">
                                    <span class="contact-name"><?= htmlspecialchars($contact['name']) ?></span>
                                    <span class="message-time"><?= $contact['last_message_time'] ? date("H:i", strtotime($contact['last_message_time'])) : '' ?></span>
                                </div>
                                <div class="last-message-preview">
                                    <p><?= htmlspecialchars(mb_strimwidth($contact['last_message'] ?? 'No messages yet', 0, 40, "...")) ?></p>
                                    <?php if ($contact['unread_count'] > 0) : ?>
                                        <span class="unread-count"><?= $contact['unread_count'] ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        // WebSocket Real-time connection with reconnect
        let socket;
        let reconnectAttempts = 0;
        const maxReconnectAttempts = 5;
        const reconnectDelay = 3000; // ms

        function connectWebSocket() {
            const userId = <?= json_encode($_SESSION['user_id']) ?>;
            const protocol = window.location.protocol === 'https:' ? 'wss://' : 'ws://';
            const host = window.location.hostname;
            socket = new WebSocket(`${protocol}${host}:8080?user_id=${userId}`);

            socket.onopen = () => {
                console.log('✅ Connected to WebSocket (contacts page)');
                reconnectAttempts = 0;
                // Send presence online
                socket.send(JSON.stringify({
                    type: 'presence',
                    status: 'online'
                }));
            };

            socket.onmessage = event => {
                try {
                    const data = JSON.parse(event.data);

                    switch (data.type) {
                        case 'message':
                            updateContactLastMessage(data);
                            break;
                        case 'presence':
                            updateContactPresence(data.user_id, data.status);
                            break;
                        case 'typing':
                            showTypingIndicator(data.from, data.is_typing);
                            break;
                        default:
                            console.log('Unknown message type:', data.type);
                    }
                } catch (err) {
                    console.error('Error parsing WebSocket message:', err);
                }
            };

            socket.onerror = err => {
                console.error('WebSocket error:', err);
            };

            socket.onclose = () => {
                if (reconnectAttempts < maxReconnectAttempts) {
                    console.warn(`WebSocket disconnected. Reconnecting in ${reconnectDelay / 1000}s... (${reconnectAttempts + 1}/${maxReconnectAttempts})`);
                    setTimeout(connectWebSocket, reconnectDelay);
                    reconnectAttempts++;
                } else {
                    console.error('Max reconnection attempts reached. Please refresh.');
                }
            };
        }

        function updateContactLastMessage(data) {
            const contact = document.querySelector(`.contact-item[data-contact-id="${data.from}"]`);
            if (!contact) return;

            // Update message preview
            const preview = contact.querySelector('.last-message-preview p');
            preview.textContent = data.content.length > 40 ? data.content.slice(0, 40) + '...' : data.content;

            // Update time
            const timeElem = contact.querySelector('.message-time');
            const now = new Date();
            timeElem.textContent = now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });

            // Update unread badge count
            let badge = contact.querySelector('.unread-count');
            if (badge) {
                badge.textContent = parseInt(badge.textContent) + 1;
            } else {
                badge = document.createElement('span');
                badge.className = 'unread-count';
                badge.textContent = '1';
                contact.querySelector('.last-message-preview').appendChild(badge);
            }

            // Move contact to top
            const parent = contact.parentNode;
            parent.prepend(contact);
        }

        function updateContactPresence(userId, status) {
            const contact = document.querySelector(`.contact-item[data-contact-id="${userId}"]`);
            if (!contact) return;

            const avatar = contact.querySelector('.contact-avatar');
            let badge = avatar.querySelector('.online-badge');

            if (status === 'online') {
                if (!badge) {
                    badge = document.createElement('span');
                    badge.className = 'online-badge';
                    avatar.appendChild(badge);
                }
            } else {
                if (badge) badge.remove();
            }
        }

        function showTypingIndicator(userId, isTyping) {
            const contact = document.querySelector(`.contact-item[data-contact-id="${userId}"]`);
            if (!contact) return;

            const preview = contact.querySelector('.last-message-preview p');
            if (isTyping) {
                preview.textContent = 'typing...';
            } else {
                preview.textContent = preview.dataset.lastMessage || preview.textContent;
            }
        }

        document.addEventListener('DOMContentLoaded', () => {
            connectWebSocket();

            // Store original messages for typing restoration
            document.querySelectorAll('.contact-item').forEach(item => {
                const preview = item.querySelector('.last-message-preview p');
                preview.dataset.lastMessage = preview.textContent;
            });

            // Search functionality
            const searchInput = document.querySelector('.search-bar input');
            searchInput.addEventListener('input', () => {
                const term = searchInput.value.toLowerCase().trim();
                document.querySelectorAll('.contact-item').forEach(item => {
                    const name = item.querySelector('.contact-name').textContent.toLowerCase();
                    const msg = item.querySelector('.last-message-preview p').textContent.toLowerCase();
                    item.style.display = (name.includes(term) || msg.includes(term)) ? 'flex' : 'none';
                });
            });
        });

        // Close socket on page unload
        window.addEventListener('beforeunload', () => {
            if (socket && socket.readyState === WebSocket.OPEN) {
                socket.close();
            }
        });
    </script>
</body>

</html>
