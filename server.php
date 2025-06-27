<!-- WebSocket server bootstrap script that uses Ratchet to run your ChatServer class: -->
<?php
require 'ChatServer.php'; 
// Include the ChatServer class file (the WebSocket message handler you created earlier)

use Ratchet\Server\IoServer; 
// Import Ratchet's IoServer class, which handles input/output and runs the event loop

use Ratchet\Http\HttpServer; 
// Import HttpServer class, which handles the HTTP upgrade request (HTTP -> WebSocket handshake)

use Ratchet\WebSocket\WsServer; 
// Import WsServer class which wraps your ChatServer and manages WebSocket protocol specifics

// Create and configure the server:
$server = IoServer::factory(
    // Wrap your ChatServer in WsServer to enable WebSocket support
    new HttpServer(
        new WsServer(
            new ChatServer()
        )
    ),
    8080 // The TCP port number on which the WebSocket server listens; must match your JS WebSocket URL port
);

echo "✅ WebSocket Server started on port 8080...\n"; 
// Output to console indicating the server has started successfully on port 8080

$server->run(); 
// Start the event loop and keep the server running, accepting connections and messages
