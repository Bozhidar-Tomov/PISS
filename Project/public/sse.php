<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');
header('X-Accel-Buffering: no');

set_time_limit(0); // No time limit
if (ob_get_level()) ob_end_clean();

if (!isset($_SESSION['user'])) {
    echo "event: error\n";
    echo "data: {\"message\": \"Unauthorized\"}\n\n";
    flush();
    exit;
}

require_once __DIR__ . '/../app/Services/CommandService.php';
require_once __DIR__ . '/../app/Database/Database.php';
use App\Services\CommandService;
use App\Database\Database;

$userId = $_SESSION['user']['id'];

$commandService = new CommandService();
$commandService->registerActiveUser($userId);

echo "event: connected\n";
echo "data: {\"message\": \"Connected to SSE server\", \"userId\": \"" . $userId . "\"}\n\n";
flush();

// Close session to prevent blocking other requests
session_write_close();

// Keep connection alive with periodic heartbeats
$lastEventTime = time();
$lastCheckTime = time();
$lastActiveUpdateTime = time();

// The script will run for 30 minutes max before client needs to reconnect
$endTime = time() + (30 * 60);

while (time() < $endTime) {
    try {
        // Update active status every 15 seconds
        if (time() - $lastActiveUpdateTime >= 15) {
            $commandService->registerActiveUser($userId);
            $lastActiveUpdateTime = time();
        }
        
        // Check for new commands every 2 seconds
        if (time() - $lastCheckTime >= 2) {
            $command = checkForNewCommands($commandService, $userId);
            $lastCheckTime = time();
            
            if ($command) {
                echo "event: command\n";
                echo "data: " . json_encode($command) . "\n\n";
                flush();
                $lastEventTime = time();
            }
            
            // Check for transfer messages specifically
            $transferMessage = checkForTransferMessages($userId);
            if ($transferMessage) {
                echo "event: command\n";
                echo "data: " . json_encode($transferMessage) . "\n\n";
                flush();
                $lastEventTime = time();
            }
        }
        
        // Send heartbeat every 15 seconds
        if (time() - $lastEventTime >= 15) {
            $activeUsers = $commandService->getActiveUserCount();
            echo "event: heartbeat\n";
            echo "data: {\"time\": " . time() . ", \"activeUsers\": " . $activeUsers . "}\n\n";
            flush();
            $lastEventTime = time();
        }
        
        // Sleep to prevent CPU hogging
        usleep(500000); // Sleep for 0.5 seconds
    } catch (Exception $e) {
        // Log error but continue the loop
        error_log("SSE Error: " . $e->getMessage());
        usleep(1000000); // Sleep for 1 second on error
    }
}

$commandService->removeActiveUser($userId);

function checkForNewCommands($commandService, $userId) {
    // Get the active command
    $command = $commandService->getActiveCommand();
    
    if (!$command || empty($command['id'])) {
        return null;
    }
    
    // Special handling for transfer messages
    if ($command['type'] === 'transfer_message') {
        // Check if this transfer message is for this specific user
        if (isset($command['toUserId']) && $command['toUserId'] === $userId) {
            return $command;
        }
        return null;
    }
    
    // Check if the command has expired
    $timestamp = $command['timestamp'] ?? 0;
    $duration = $command['duration'] ?? 0;
    
    if ($duration > 0 && time() > ($timestamp + $duration)) {
        return null;
    }
    
    // Check if the user matches the target filters
    if (!$commandService->userMatchesCommandFilters($userId, $command)) {
        return null;
    }
    
    return $command;
}

function checkForTransferMessages($userId) {
    try {
        $db = Database::getInstance();
        
        // Get unread transfer messages for this user
        $stmt = $db->query(
            "SELECT c.* FROM commands c 
             WHERE c.command_type = 'transfer_message' 
             AND c.is_active = 1 
             AND JSON_EXTRACT(c.command_data, '$.toUserId') = ?
             ORDER BY c.timestamp DESC 
             LIMIT 1",
            [$userId]
        );
        
        $command = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($command) {
            $commandData = json_decode($command['command_data'], true);
            
            // Mark this command as inactive so it won't be sent again
            $db->query(
                "UPDATE commands SET is_active = 0 WHERE id = ?",
                [$command['id']]
            );
            
            return [
                'id' => $command['id'],
                'type' => 'transfer_message',
                'fromUsername' => $commandData['fromUsername'],
                'message' => $commandData['message'],
                'amount' => $commandData['amount'],
                'timestamp' => $command['timestamp']
            ];
        }
        
        return null;
    } catch (Exception $e) {
        return null;
    }
} 