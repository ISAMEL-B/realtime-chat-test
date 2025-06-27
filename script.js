document.addEventListener('DOMContentLoaded', function() {
    // Common elements
    const messageInput = document.getElementById('message-input');
    const sendButton = document.getElementById('send-button');
    const messagesContainer = document.getElementById('messages-container');
    
    // Check if we're on the conversation page
    if (messageInput && sendButton && messagesContainer) {
        const contactId = typeof contactId !== 'undefined' ? contactId : null;
        
        // Scroll to bottom of messages
        scrollToBottom();
        
        // Send message on button click
        sendButton.addEventListener('click', sendMessage);
        
        // Send message on Enter key
        messageInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                sendMessage();
            }
        });
        
        // Check for new messages periodically
        if (contactId) {
            setInterval(checkNewMessages, 2000);
        }
    }
    
    // Mobile navigation
    const backButton = document.querySelector('.back-button');
    if (backButton) {
        backButton.addEventListener('click', function(e) {
            e.preventDefault();
            document.body.classList.add('show-contacts');
            document.body.classList.remove('show-chat');
        });
    }
    
    // Contact items click (for mobile)
    const contactItems = document.querySelectorAll('.contact-item');
    contactItems.forEach(item => {
        item.addEventListener('click', function(e) {
            if (window.innerWidth <= 768) {
                e.preventDefault();
                document.body.classList.add('show-chat');
                document.body.classList.remove('show-contacts');
                // Here you would load the chat content via AJAX
                window.location.href = this.href;
            }
        });
    });
});

function scrollToBottom() {
    const messagesContainer = document.getElementById('messages-container');
    if (messagesContainer) {
        messagesContainer.scrollTop = messagesContainer.scrollHeight;
    }
}

function sendMessage() {
    const messageInput = document.getElementById('message-input');
    const message = messageInput.value.trim();
    
    if (message && contactId) {
        fetch('send_message.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `receiver_id=${contactId}&message=${encodeURIComponent(message)}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Add message to UI
                addMessageToUI({
                    id: data.message.id,
                    sender_id: currentUserId,
                    content: data.message.content,
                    time: data.message.time,
                    is_read: data.message.is_read
                }, true);
                
                // Clear input
                messageInput.value = '';
                
                // Scroll to bottom
                scrollToBottom();
            }
        })
        .catch(error => console.error('Error:', error));
    }
}

function addMessageToUI(message, isSent) {
    const messagesContainer = document.getElementById('messages-container');
    if (!messagesContainer) return;
    
    const messageDiv = document.createElement('div');
    messageDiv.className = `message ${isSent ? 'sent' : 'received'}`;
    
    const statusIcon = isSent ? (message.is_read ? '✓✓' : '✓') : '';
    
    messageDiv.innerHTML = `
        <div class="message-content">
            <p>${escapeHtml(message.content)}</p>
            <span class="message-time">
                ${message.time}
                ${statusIcon ? `<span class="message-status">${statusIcon}</span>` : ''}
            </span>
        </div>
    `;
    
    messagesContainer.appendChild(messageDiv);
}

function checkNewMessages() {
    const messagesContainer = document.getElementById('messages-container');
    if (!messagesContainer || !contactId) return;
    
    const lastMessageId = getLastMessageId();
    
    fetch(`check_new_messages.php?contact_id=${contactId}&last_message_id=${lastMessageId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.messages.length > 0) {
                data.messages.forEach(message => {
                    addMessageToUI(message, message.sender_id == currentUserId);
                });
                scrollToBottom();
            }
        })
        .catch(error => console.error('Error:', error));
}

function getLastMessageId() {
    const messages = document.querySelectorAll('.message');
    if (messages.length === 0) return 0;
    
    const lastMessage = messages[messages.length - 1];
    // In a real app, you'd get the ID from a data attribute
    return 0; // Simplified for this example
}

function escapeHtml(unsafe) {
    return unsafe
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
}