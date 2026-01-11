<?php
header('Content-Type: application/json');

require_once __DIR__ . '/../../app/Models/User.php';
require_once __DIR__ . '/../../app/Database/Database.php';

use App\Models\User;
use App\Database\Database;

function log_mic_error($msg, $data = null) {
    $logFile = __DIR__ . '/../../app/Database/mic_results_errors.log';
    $entry = date('c') . ' | ' . $msg;
    if ($data !== null) {
        $entry .= ' | ' . json_encode($data);
    }
    file_put_contents($logFile, $entry . "\n", FILE_APPEND);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    log_mic_error('Method not allowed', $_SERVER);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$required = ['userId', 'commandId', 'intensity', 'volume', 'reactionAccuracy'];
$missing = [];
$invalid = [];

foreach ($required as $field) {
    if (!array_key_exists($field, $data)) {
        $missing[] = $field;
        continue;
    }

    // Perform basic validation without treating 0 as missing
    switch ($field) {
        case 'intensity':
        case 'volume':
        case 'reactionAccuracy':
            if (!is_numeric($data[$field])) {
                $invalid[] = $field;
            }
            break;
        default:
            if ($data[$field] === '' || $data[$field] === null) {
                $invalid[] = $field;
            }
    }
}

if (!$data || count($missing) > 0) {
    http_response_code(400);
    log_mic_error('Missing required fields: ' . implode(',', $missing), $data);
    echo json_encode([
        'success' => false,
        'error'   => 'Missing required fields',
        'missing' => $missing,
    ]);
    exit;
}

if (count($invalid) > 0) {
    http_response_code(400);
    log_mic_error('Invalid field values: ' . implode(',', $invalid), $data);
    echo json_encode([
        'success' => false,
        'error'   => 'Invalid field values',
        'invalid' => $invalid,
    ]);
    exit;
}

$db = Database::getInstance();

try {
    // Check if result already exists
    $stmt = $db->query(
        "SELECT id FROM mic_results WHERE user_id = ? AND command_id = ?",
        [$data['userId'], $data['commandId']]
    );
    
    if ($stmt->rowCount() > 0) {
        // Update existing result
        $db->query(
            "UPDATE mic_results SET intensity = ?, volume = ?, reaction_accuracy = ?, timestamp = ? 
             WHERE user_id = ? AND command_id = ?",
            [
                $data['intensity'],
                $data['volume'],
                $data['reactionAccuracy'],
                time(),
                $data['userId'],
                $data['commandId']
            ]
        );
    } else {
        // Insert new result
        $db->query(
            "INSERT INTO mic_results (user_id, command_id, intensity, volume, reaction_accuracy, timestamp) 
             VALUES (?, ?, ?, ?, ?, ?)",
            [
                $data['userId'],
                $data['commandId'],
                $data['intensity'],
                $data['volume'],
                $data['reactionAccuracy'],
                time()
            ]
        );
    }
} catch (PDOException $e) {
    http_response_code(500);
    log_mic_error('Database error: ' . $e->getMessage(), $data);
    echo json_encode(['success' => false, 'error' => 'Database error']);
    exit;
}

// Award points
$basePoints = 5;
$accuracyBonus = round($data['reactionAccuracy'] * 0.5); // up to 50
// Get target intensity from active command
$intensityBonus = 0;

try {
    // Get active command
    $stmt = $db->query(
        "SELECT command_data FROM commands WHERE is_active = 1 ORDER BY timestamp DESC LIMIT 1"
    );
    $activeCmd = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($activeCmd && isset($activeCmd['command_data'])) {
        $activeCmd = json_decode($activeCmd['command_data'], true);
        $targetIntensity = isset($activeCmd['intensity']) ? (int)$activeCmd['intensity'] : 0;
        $userIntensity = isset($data['intensity']) ? (int)$data['intensity'] : 0;
        $diff = abs($userIntensity - $targetIntensity);
        // More forgiving: within 10 gets most points, falls off gently
        $intensityBonus = max(0, 45 - round(($diff / 100) * 45 * 1.5));
    }
    
    // Check if simulated audience is on
    $stmt = $db->query(
        "SELECT setting_value FROM settings WHERE setting_key = 'sim_audience_on'"
    );
    $simAudienceSetting = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($simAudienceSetting && $simAudienceSetting['setting_value'] === '1') {
        $totalPoints = $basePoints + $accuracyBonus + $intensityBonus;
    } else {
        $totalPoints = $basePoints + $accuracyBonus;
    }
    
    // Add points to user
    User::addPoints($data['userId'], $totalPoints);
    
    // Get new points value
    $stmt = $db->query(
        "SELECT points FROM users WHERE id = ?",
        [$data['userId']]
    );
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    $newPoints = $user ? (int)$user['points'] : 0;
    
    echo json_encode(['success' => true, 'points' => $newPoints]);
} catch (PDOException $e) {
    http_response_code(500);
    log_mic_error('Database error: ' . $e->getMessage(), $data);
    echo json_encode(['success' => false, 'error' => 'Database error']);
    exit;
} 