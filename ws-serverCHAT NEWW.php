<?php
require 'vendor/autoload.php'; // Ensure you've run: composer require cboden/ratchet

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
        parse_str($conn->httpRequest->getUri()->getQuery(), $queryParams);
        $userId = $queryParams['user_id'] ?? null;

        if (!$userId || !is_numeric($userId)) {
            echo "[ERROR] Connection rejected: missing or invalid user_id\n";
            $conn->close();
            return;
        }

        $this->clients->attach($conn);
        $this->userConnections[$userId] = $conn;
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

            case 'typing':
                $this->handleTyping($from, $data);
                break;

            case 'read_receipt':
                $this->handleReadReceipt($from, $data);
                break;

            case 'presence':
                $this->handlePresence($from, $data);
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
        $messageContent = htmlspecialchars($data['content']);

        echo "[MESSAGE] $fromId -> $toId: $messageContent\n";

        $payload = json_encode([
            'type' => 'message',
            'from' => $fromId,
            'content' => $messageContent,
            'time' => date('H:i:s'),
        ]);

        if (isset($this->userConnections[$toId])) {
            $this->userConnections[$toId]->send($payload);
            echo "[DELIVERED] to $toId\n";
        } else {
            echo "[OFFLINE] User $toId not online\n";
        }

        // Optional feedback to sender
        $from->send(json_encode([
            'type' => 'status',
            'message' => "Message sent to $toId"
        ]));
    }

    protected function handleTyping(ConnectionInterface $from, array $data)
    {
        if (!isset($data['to']) || !isset($data['is_typing'])) {
            echo "[WARNING] Invalid typing data\n";
            return;
        }

        $toId = (int)$data['to'];
        $fromId = (int)$data['from'];

        $payload = json_encode([
            'type' => 'typing',
            'from' => $fromId,
            'is_typing' => (bool)$data['is_typing']
        ]);

        if (isset($this->userConnections[$toId])) {
            $this->userConnections[$toId]->send($payload);
        }

        echo "[TYPING] User $fromId is " . ($data['is_typing'] ? 'typing' : 'not typing') . " to User $toId\n";
    }

    protected function handleReadReceipt(ConnectionInterface $from, array $data)
    {
        if (!isset($data['message_id'], $data['reader_id'])) {
            echo "[WARNING] Invalid read_receipt data\n";
            return;
        }

        $payload = json_encode([
            'type' => 'read_receipt',
            'read_by' => $data['reader_id'],
            'message_id' => $data['message_id']
        ]);

        $senderId = $data['sender_id'] ?? null;
        if ($senderId && isset($this->userConnections[$senderId])) {
            $this->userConnections[$senderId]->send($payload);
        }

        echo "[READ] Message {$data['message_id']} read by User {$data['reader_id']}\n";
    }

    protected function handlePresence(ConnectionInterface $from, array $data)
    {
        if (!isset($data['user_id'], $data['status'])) {
            echo "[WARNING] Invalid presence data\n";
            return;
        }

        $payload = json_encode([
            'type' => 'presence',
            'user_id' => $data['user_id'],
            'status' => $data['status']
        ]);

        // Broadcast presence to all other users
        foreach ($this->userConnections as $uid => $conn) {
            if ($uid != $data['user_id']) {
                $conn->send($payload);
            }
        }

        echo "[PRESENCE] User {$data['user_id']} is now {$data['status']}\n";
    }

    public function onClose(ConnectionInterface $conn)
    {
        $this->clients->detach($conn);

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

// Start WebSocket server
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