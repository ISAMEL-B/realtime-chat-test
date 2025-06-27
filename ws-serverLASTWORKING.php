<?php
require 'vendor/autoload.php'; // Adjust path if needed
// require __DIR__ . '/../vendor/autoload.php'; // Adjust path if needed

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;

class ChatServer implements MessageComponentInterface
{
    protected \SplObjectStorage $clients;
    protected array $userConnections = []; // user_id => ConnectionInterface

    public function __construct()
    {
        $this->clients = new \SplObjectStorage();
        echo "[INFO] WebSocket server initialized\n";
    }

    public function onOpen(ConnectionInterface $conn)
    {
        // Parse query string for user_id
        parse_str($conn->httpRequest->getUri()->getQuery(), $queryParams);
        $userId = $queryParams['user_id'] ?? null;

        // Validate user_id
        if (!$userId || !is_numeric($userId)) {
            echo "[ERROR] Connection rejected: missing or invalid user_id\n";
            $conn->close();
            return;
        }

        // Register the client
        $this->clients->attach($conn);
        $this->userConnections[$userId] = $conn;

        // Store user_id on connection for easy lookup during disconnect
        $conn->userId = $userId;

        echo "[CONNECTED] User $userId connected\n";
    }

    public function onMessage(ConnectionInterface $from, $msg)
    {
        $data = json_decode($msg, true);
        if (!is_array($data) || !isset($data['type'])) {
            echo "[WARNING] Invalid message received\n";
            return;
        }

        switch ($data['type']) {
            case 'message':
                $this->handleMessage($from, $data);
                break;

            default:
                echo "[WARNING] Unknown message type: {$data['type']}\n";
        }
    }

    protected function handleMessage(ConnectionInterface $from, array $data)
    {
        $requiredFields = ['from', 'to', 'content'];

        foreach ($requiredFields as $field) {
            if (empty($data[$field])) {
                echo "[ERROR] Missing '$field' in message\n";
                return;
            }
        }

        $fromId = (int)$data['from'];
        $toId = (int)$data['to'];
        $messageContent = htmlspecialchars($data['content']); // Sanitize

        echo "[MESSAGE] $fromId -> $toId: $messageContent\n";

        // Prepare message payload
        $payload = json_encode([
            'type' => 'new_message',
            'message' => [
                'id' => uniqid(),
                'sender_id' => $fromId,
                'receiver_id' => $toId,
                'content' => $messageContent,
                'time' => date('H:i'),
                'is_read' => false
            ]
        ]);

        // Send to recipient if online
        if (isset($this->userConnections[$toId])) {
            $this->userConnections[$toId]->send($payload);
            echo "[DELIVERED] Message sent to User $toId\n";
        } else {
            echo "[PENDING] User $toId not connected, message not delivered in real-time\n";
        }

        // (Optional) Send back confirmation to sender
        if (isset($this->userConnections[$fromId])) {
            $this->userConnections[$fromId]->send($payload);
        }
    }

    public function onClose(ConnectionInterface $conn)
    {
        $this->clients->detach($conn);

        // Remove user from userConnections
        if (isset($conn->userId) && isset($this->userConnections[$conn->userId])) {
            unset($this->userConnections[$conn->userId]);
            echo "[DISCONNECTED] User {$conn->userId} disconnected\n";
        }
    }

    public function onError(ConnectionInterface $conn, \Exception $e)
    {
        echo "[ERROR] {$e->getMessage()}\n";
        $conn->close();
    }
}

// Start the server
$port = 8080;
echo "[STARTING] WebSocket server listening on port $port...\n";

$server = \Ratchet\Server\IoServer::factory(
    new \Ratchet\Http\HttpServer(
        new \Ratchet\WebSocket\WsServer(
            new ChatServer()
        )
    ),
    $port
);

$server->run();
