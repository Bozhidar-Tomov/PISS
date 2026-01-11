<?php
// Prevent any PHP errors from being output as HTML
error_reporting(0);
ini_set('display_errors', 0);

header('Content-Type: application/json');
session_start();

require_once __DIR__ . '/../../app/Models/User.php';
require_once __DIR__ . '/../../app/Database/Database.php';

use App\Models\User;
use App\Database\Database;

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Method not allowed']);
        exit;
    }

    $data = json_decode(file_get_contents('php://input'), true);

    // Fallback to form-encoded POST if JSON is empty
    if (empty($data)) {
        $fromUserId = $_POST['fromUserId'] ?? '';
        $toUsername = trim($_POST['toUsername'] ?? $_POST['recipient'] ?? '');
        $amount = (int)($_POST['amount'] ?? 0);
        $message = trim($_POST['message'] ?? '');
    } else {
        $fromUserId = $data['fromUserId'] ?? '';
        $toUsername = trim($data['toUsername'] ?? '');
        $amount = (int)($data['amount'] ?? 0);
        $message = trim($data['message'] ?? '');
    }

    if (!$fromUserId || !$toUsername || $amount <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Missing or invalid fields']);
        exit;
    }


    $db = Database::getInstance();

    // Check if sender exists and has enough points
    $stmt = $db->query(
        "SELECT id, points FROM users WHERE id = ?",
        [$fromUserId]
    );
    $sender = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$sender) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Sender not found']);
        exit;
    }
    
    if ($sender['points'] < $amount) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Insufficient points']);
        exit;
    }
    
    // Find recipient by username
    $stmt = $db->query(
        "SELECT id FROM users WHERE LOWER(username) = LOWER(?)",
        [$toUsername]
    );
    $recipient = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$recipient) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Recipient not found']);
        exit;
    }
    
    // Transfer points
    $result = User::transferPoints($fromUserId, $toUsername, $amount, $message);
    
    // Debug: log the result
    error_log("Transfer result: " . json_encode($result));
    
    // If transfer was successful, trigger SSE notification (even if message is empty)
    if ($result['success']) {
        try {
            // Store notification for SSE to pick up
            $db->query(
                "INSERT INTO commands (id, command_type, command_data, is_active, timestamp) 
                 VALUES (?, ?, ?, ?, ?)",
                [
                    'transfer_msg_' . $result['transferId'],
                    'transfer_message',
                    json_encode([
                        'fromUsername' => $result['fromUsername'],
                        'toUserId' => $result['toUserId'],
                        'message' => $message,
                        'amount' => $amount
                    ]),
                    true,
                    time()
            ]
            );
        } catch (Exception $e) {
            error_log("SSE notification error: " . $e->getMessage());
            // Don't fail the transfer if SSE notification fails
        }
    }
    
    if ($result['success']) {
        // Get updated points balance
        $stmt = $db->query(
            "SELECT points FROM users WHERE id = ?",
            [$fromUserId]
        );
        $updatedSender = $stmt->fetch(PDO::FETCH_ASSOC);
        $newBalance = $updatedSender['points'];
        
        // Update session data
        $_SESSION['user']['points'] = $newBalance;
        
        $result['newBalance'] = $newBalance;
        $result['message'] = "Successfully transferred $amount points to $toUsername";
    }
    
    echo json_encode($result);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
    exit;
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Unexpected error: ' . $e->getMessage()]);
    exit;
} 