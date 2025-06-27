<?php
require 'vendor/autoload.php';  // Load all composer packages, including Ratchet for WebSocket support

use Ratchet\MessageComponentInterface;  // Interface defining required WebSocket methods (onOpen, onMessage, etc.)
use Ratchet\ConnectionInterface;       // Interface representing a client connection

class ChatServer implements MessageComponentInterface
{
    // Storage for all connected clients (connections)
    protected \SplObjectStorage $clients;

    // Map of user_id to their WebSocket connection (to easily find a user's connection)
    protected array $userConnections = []; // user_id => ConnectionInterface

    // Constructor initializes the clients storage and outputs startup message
    public function __construct()
    {
        $this->clients = new \SplObjectStorage();  // Instantiate object storage for connections
        echo "✅ WebSocket Server started on port 8080...\n";  // Log server start
    }

    // Called when a new client connects
    public function onOpen(ConnectionInterface $conn)
    {
        // Extract the query string parameters from the HTTP upgrade request (to get user_id)
        parse_str($conn->httpRequest->getUri()->getQuery(), $queryParams);
        $userId = isset($queryParams['user_id']) ? (int)$queryParams['user_id'] : 0;

        // If user_id missing or invalid, reject connection
        if ($userId <= 0) {
            echo "❌ Invalid connection rejected (missing user_id)\n";
            $conn->close();  // Close this connection immediately
            return;          // Stop processing
        }

        // Attach user_id to connection object for easy access later
        $conn->user_id = $userId;

        // Add connection to clients collection
        $this->clients->attach($conn);

        // Map this user_id to this connection (for quick lookup when sending messages)
        $this->userConnections[$userId] = $conn;

        // Log new connection and how many users are connected now
        echo "✅ User {$userId} connected. Total: " . count($this->userConnections) . "\n";

        // Broadcast to all other users that this user is now online
        $this->broadcastPresence($userId, 'online');
    }

    // Called when this client sends a message to the server
    public function onMessage(ConnectionInterface $from, $msg)
    {
        // Get sender's user_id attached earlier
        $userId = $from->user_id ?? 0;

        // Decode incoming JSON message from client
        $data = json_decode($msg, true);

        // If message is invalid or missing type, ignore it
        if (!$data || !isset($data['type'])) return;

        // Switch based on message type for different event handling
        switch ($data['type']) {
            case 'message':
                $recipientId = $data['to'] ?? 0;

                // Send message payload to recipient if connected
                $this->sendMessageToRecipient($recipientId, [
                    'type' => 'message',
                    'message' => [
                        'sender_id' => $userId,
                        'receiver_id' => $recipientId,
                        'content' => $data['content'],
                        'time' => $data['time'] ?? date('H:i'),
                    ]
                ]);

                // Optionally send delivery confirmation back to sender
                if (isset($this->userConnections[$userId])) {
                    $this->userConnections[$userId]->send(json_encode([
                        'type' => 'message_sent',
                        'to' => $recipientId,
                        'content' => $data['content'],
                        'time' => $data['time'] ?? date('H:i'),
                    ]));
                }
                break;

            case 'typing':
                // Forward typing indicator to the recipient user
                $this->sendMessageToRecipient($data['to'], [
                    'type' => 'typing',
                    'from' => $userId,
                    'is_typing' => $data['is_typing']
                ]);
                break;

            case 'presence':
                // Broadcast user presence status (online/offline) to others
                $this->broadcastPresence($userId, $data['status']);
                break;

            case 'read_receipt':
                // Notify the original sender that the message was read by this user
                $this->sendMessageToRecipient($data['reader_id'], [
                    'type' => 'read_receipt',
                    'read_by' => $userId,
                    'message_id' => $data['message_id']
                ]);
                break;

            default:
                // Unknown message type received, log a warning for debugging
                echo "⚠️ Unknown message type from user {$userId}: {$data['type']}\n";
                break;
        }
    }

    // Called when a client disconnects
    public function onClose(ConnectionInterface $conn)
    {
        $userId = $conn->user_id ?? null;

        // Remove this user connection from the map if exists
        if ($userId !== null && isset($this->userConnections[$userId])) {
            unset($this->userConnections[$userId]);
        }

        // Remove connection from clients collection
        $this->clients->detach($conn);

        // Log disconnection and remaining users count
        echo "❌ User {$userId} disconnected. Remaining: " . count($this->userConnections) . "\n";

        // Broadcast to others that this user went offline
        $this->broadcastPresence($userId, 'offline');
    }

    // Called if a connection error occurs
    public function onError(ConnectionInterface $conn, \Exception $e)
    {
        $userId = $conn->user_id ?? 'Unknown';

        // Log error message
        echo "⚠️ Error for user {$userId}: {$e->getMessage()}\n";

        // Close this connection after error
        $conn->close();
    }

    // Helper function: Send JSON message payload to a specific recipient if connected
    protected function sendMessageToRecipient($recipientId, array $payload)
    {
        if (isset($this->userConnections[$recipientId])) {
            $conn = $this->userConnections[$recipientId];
            $conn->send(json_encode($payload));  // Send JSON encoded message
        }
    }

    // Helper function: Broadcast presence status to all users except the one who changed status
    protected function broadcastPresence($userId, string $status)
    {
        foreach ($this->userConnections as $uid => $conn) {
            if ($uid != $userId) {
                $conn->send(json_encode([
                    'type' => 'presence',
                    'user_id' => $userId,
                    'status' => $status
                ]));
            }
        }
    }
}
// End of ChatServer class definition