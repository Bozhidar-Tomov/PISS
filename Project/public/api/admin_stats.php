<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is admin
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/../../app/Services/CommandService.php';
require_once __DIR__ . '/../../app/Database/Database.php';

use App\Services\CommandService;
use App\Database\Database;

$db = Database::getInstance();
$commandService = new CommandService();

// Get active users count and command data
$activeUsers = $commandService->getActiveUserCount();
$activeCmd = $commandService->getActiveCommand();
$cmdId = $activeCmd['id'] ?? null;

// Initialize response data
$response = [
    'activeUsers' => $activeUsers,
    'currentVolume' => '0 dB',
    'responseRate' => '0%',
    'sseStatus' => $activeUsers > 0 ? 'online' : 'offline',
    'statusText' => $activeUsers > 0 ? 'SSE Server: Online' : 'SSE Server: Offline',
    'lastCommand' => null
];

// Calculate average volume for current command
$avgVolume = 0;
if ($cmdId) {
    try {
        $stmt = $db->query(
            "SELECT AVG(volume) as avg_volume FROM mic_results WHERE command_id = ?",
            [$cmdId]
        );
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result && isset($result['avg_volume'])) {
            $avgVolume = round($result['avg_volume']);
        }
    } catch (PDOException $e) {
        // Error handled silently
    }
}
$response['currentVolume'] = $avgVolume . ' dB';

// Calculate response rate
$rate = 0;
if ($cmdId && $activeUsers > 0) {
    try {
        $stmt = $db->query(
            "SELECT COUNT(DISTINCT user_id) as count FROM mic_results 
             WHERE command_id = ? AND reaction_accuracy >= 15",
            [$cmdId]
        );
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result) {
            $numResponded = (int)$result['count'];
            $rate = round(($numResponded / $activeUsers) * 100);
        }
    } catch (PDOException $e) {
        // Error handled silently
    }
}
$response['responseRate'] = $rate . '%';

// Include last command info if available
if (!empty($_SESSION['last_command'])) {
    $response['lastCommand'] = $_SESSION['last_command'];
}

// Return JSON response
header('Content-Type: application/json');
echo json_encode($response); 