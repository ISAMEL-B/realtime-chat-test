<?php
// cron/process_failed_notifications.php
require __DIR__ . '/../db.php';

try {
    $db->beginTransaction();
    
    // Get pending notifications (max 5 attempts)
    $pending = $db->query("
        SELECT * FROM pending_ws_notifications 
        WHERE attempts < 5 
        ORDER BY created_at ASC 
        LIMIT 100
    ")->fetchAll(PDO::FETCH_ASSOC);

    foreach ($pending as $notification) {
        $data = json_decode($notification['notification_data'], true);
        $notificationId = $notification['id'];
        
        // Try to send via WebSocket
        $fp = @stream_socket_client("tcp://127.0.0.1:8080", $errno, $errstr, 1);
        if ($fp) {
            fwrite($fp, json_encode($data) . "\n");
            fclose($fp);
            
            // Delete on success
            $db->prepare("DELETE FROM pending_ws_notifications WHERE id = ?")
               ->execute([$notificationId]);
        } else {
            // Update attempt count on failure
            $db->prepare("
                UPDATE pending_ws_notifications 
                SET attempts = attempts + 1, last_attempt = NOW() 
                WHERE id = ?
            ")->execute([$notificationId]);
            
            error_log("Failed to resend notification {$notificationId}: {$errstr}");
        }
    }
    
    $db->commit();
    
    echo "Processed " . count($pending) . " notifications\n";
    
} catch (PDOException $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    error_log("Notification processing error: " . $e->getMessage());
    http_response_code(500);
    echo "Error processing notifications";
}