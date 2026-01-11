<?php

require_once __DIR__ . '/BaseController.php';
require_once __DIR__ . '/../Services/CommandService.php';
require_once __DIR__ . '/../Services/ExternalApiService.php';
require_once __DIR__ . '/../Database/Database.php';
require_once __DIR__ . '/../Models/User.php';

use App\Services\CommandService;
use App\Services\ExternalApiService;
use App\Database\Database;
use App\Models\User;

class ApiController extends BaseController
{
    private $db;
    private $commandService;
    private $externalService;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->commandService = new CommandService();
        $this->externalService = new ExternalApiService();
        
        // Ensure session is started for all API calls
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    private function jsonResponse($data, $statusCode = 200)
    {
        header('Content-Type: application/json');
        http_response_code($statusCode);
        echo json_encode($data);
        exit;
    }

    // GET /api/v1/quote
    public function getQuote()
    {
        $quote = $this->externalService->getDailyQuote();
        $this->jsonResponse($quote);
    }

    // GET /api/v1/admin/stats
    public function getStats()
    {
        // Auth check
        if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
            $this->jsonResponse(['error' => 'Unauthorized'], 403);
        }

        $activeUsers = $this->commandService->getActiveUserCount();
        $activeCmd = $this->commandService->getActiveCommand();
        $cmdId = $activeCmd['id'] ?? null;

        $response = [
            'activeUsers' => $activeUsers,
            'currentVolume' => '0 dB',
            'responseRate' => '0%',
            'sseStatus' => $activeUsers > 0 ? 'online' : 'offline',
            'statusText' => $activeUsers > 0 ? 'SSE Server: Online' : 'SSE Server: Offline',
            'lastCommand' => null
        ];

        // Avg Volume
        $avgVolume = 0;
        if ($cmdId) {
            try {
                $stmt = $this->db->query(
                    "SELECT AVG(volume) as avg_volume FROM mic_results WHERE command_id = ?",
                    [$cmdId]
                );
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($result && isset($result['avg_volume'])) {
                    $avgVolume = round($result['avg_volume']);
                }
            } catch (PDOException $e) {}
        }
        $response['currentVolume'] = $avgVolume . ' dB';

        // Response Rate
        $rate = 0;
        if ($cmdId && $activeUsers > 0) {
            try {
                $stmt = $this->db->query(
                    "SELECT COUNT(DISTINCT user_id) as count FROM mic_results 
                     WHERE command_id = ? AND reaction_accuracy >= 15",
                    [$cmdId]
                );
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($result) {
                    $numResponded = (int)$result['count'];
                    $rate = round(($numResponded / $activeUsers) * 100);
                }
            } catch (PDOException $e) {}
        }
        $response['responseRate'] = $rate . '%';

        if (!empty($_SESSION['last_command'])) {
            $response['lastCommand'] = $_SESSION['last_command'];
        }

        $this->jsonResponse($response);
    }

    // GET/POST /api/v1/admin/simulated-audience
    public function toggleSimulatedAudience()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
             // Logic from toggle_sim_audience.php POST
             // Note: using $_POST fallback if JSON not parsed, but let's assume standard form post or handle both
             // The original script used $_POST['sim_audience']
             
             // Check if it's a JSON request or Form data
             $data = json_decode(file_get_contents('php://input'), true);
             $simAudience = isset($_POST['sim_audience']) || (isset($data['sim_audience']) && $data['sim_audience'] === 'on');
             
             // Actually original script checks strict existence of $_POST['sim_audience']
             // "sim_audience=on" implies $_POST['sim_audience'] is set.
             
             try {
                $value = $simAudience ? '1' : '0';
                $this->db->query(
                    "INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) 
                     ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)",
                    ['sim_audience_on', $value]
                );
                $this->jsonResponse(['success' => true, 'enabled' => $simAudience]);
             } catch (PDOException $e) {
                 $this->jsonResponse(['success' => false, 'error' => 'Database error: ' . $e->getMessage()], 500);
             }

        } else {
            // GET
            try {
                $stmt = $this->db->query(
                    "SELECT setting_value FROM settings WHERE setting_key = ?",
                    ['sim_audience_on']
                );
                $setting = $stmt->fetch(PDO::FETCH_ASSOC);
                $enabled = $setting && $setting['setting_value'] === '1';
                $this->jsonResponse(['success' => true, 'enabled' => $enabled]);
            } catch (PDOException $e) {
                $this->jsonResponse(['success' => false, 'error' => 'Database error: ' . $e->getMessage()], 500);
            }
        }
    }

    // POST /api/v1/user/categories
    public function updateCategories()
    {
        if (!isset($_SESSION['user']) || empty($_SESSION['user']['id'])) {
            $this->jsonResponse(['success' => false, 'error' => 'Unauthorized access'], 401);
        }

        $userId = $_SESSION['user']['id'];
        $categoriesInput = $_POST['categories'] ?? '';
        
        // Handle JSON input as well just in case
        if (empty($categoriesInput)) {
            $data = json_decode(file_get_contents('php://input'), true);
            if (isset($data['categories'])) {
                 $categoriesInput = is_array($data['categories']) ? implode(',', $data['categories']) : $data['categories'];
            }
        }

        $categories = array_filter(array_map('trim', explode(',', $categoriesInput)));

        if (empty($categories)) {
            $this->jsonResponse(['success' => false, 'error' => 'Please provide at least one category'], 400);
        }

        try {
            $stmt = $this->db->query("SELECT * FROM users WHERE id = ?", [$userId]);
            $userData = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$userData) {
                $this->jsonResponse(['success' => false, 'error' => 'User not found'], 404);
            }
            
            $user = new User($userData);
            $user->categories = $categories;
            
            if ($user->save()) {
                $_SESSION['user']['categories'] = $categories;
                $this->jsonResponse([
                    'success' => true,
                    'message' => 'Categories updated successfully',
                    'categories' => $categories
                ]);
            } else {
                $this->jsonResponse(['success' => false, 'error' => 'Failed to update categories'], 500);
            }
        } catch (Exception $e) {
            $this->jsonResponse(['success' => false, 'error' => 'An error occurred: ' . $e->getMessage()], 500);
        }
    }

    // POST /api/v1/points/transfer
    public function transferPoints()
    {
        $data = json_decode(file_get_contents('php://input'), true);
        
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
            $this->jsonResponse(['success' => false, 'error' => 'Missing or invalid fields'], 400);
        }

        try {
            // Check sender
            $stmt = $this->db->query("SELECT id, points FROM users WHERE id = ?", [$fromUserId]);
            $sender = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$sender) {
                $this->jsonResponse(['success' => false, 'error' => 'Sender not found'], 404);
            }
            if ($sender['points'] < $amount) {
                $this->jsonResponse(['success' => false, 'error' => 'Insufficient points'], 400);
            }
            
            // Check recipient
            $stmt = $this->db->query("SELECT id FROM users WHERE LOWER(username) = LOWER(?)", [$toUsername]);
            $recipient = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$recipient) {
                $this->jsonResponse(['success' => false, 'error' => 'Recipient not found'], 404);
            }
            
            $result = User::transferPoints($fromUserId, $toUsername, $amount, $message);
            
            if ($result['success']) {
                // SSE Trigger
                 try {
                    $this->db->query(
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
                }

                $_SESSION['user']['points'] = $result['fromNewBalance']; // Assuming method could return this or we fetch it
                // Actually the User::transferPoints doesn't explicitly return new balance in the array structure used in original file
                // But the original file re-fetched it. Let's re-fetch.
                 $stmt = $this->db->query("SELECT points FROM users WHERE id = ?", [$fromUserId]);
                 $updatedSender = $stmt->fetch(PDO::FETCH_ASSOC);
                 $_SESSION['user']['points'] = $updatedSender['points'];
                 $result['newBalance'] = $updatedSender['points']; 
                 $result['message'] = "Successfully transferred $amount points to $toUsername";
            }
            
            $this->jsonResponse($result);

        } catch (PDOException $e) {
            $this->jsonResponse(['success' => false, 'error' => 'Database error: ' . $e->getMessage()], 500);
        } catch (Exception $e) {
             $this->jsonResponse(['success' => false, 'error' => 'Unexpected error: ' . $e->getMessage()], 500);
        }
    }

    // POST /api/v1/mic-results
    public function submitMicLevel()
    {
        $data = json_decode(file_get_contents('php://input'), true);
        $required = ['userId', 'commandId', 'intensity', 'volume', 'reactionAccuracy'];
        $missing = [];
        $invalid = [];

        foreach ($required as $field) {
            if (!isset($data[$field])) {
                $missing[] = $field;
                continue;
            }
            // Basic validation
             switch ($field) {
                case 'intensity':
                case 'volume':
                case 'reactionAccuracy':
                    if (!is_numeric($data[$field])) $invalid[] = $field;
                    break;
                default:
                    if ($data[$field] === '' || $data[$field] === null) $invalid[] = $field;
            }
        }

        if ($missing) $this->jsonResponse(['success' => false, 'error' => 'Missing required fields', 'missing' => $missing], 400);
        if ($invalid) $this->jsonResponse(['success' => false, 'error' => 'Invalid field values', 'invalid' => $invalid], 400);

        try {
             // Upsert Mic Result
             $stmt = $this->db->query(
                "SELECT id FROM mic_results WHERE user_id = ? AND command_id = ?",
                [$data['userId'], $data['commandId']]
            );
            
            if ($stmt->rowCount() > 0) {
                $this->db->query(
                    "UPDATE mic_results SET intensity = ?, volume = ?, reaction_accuracy = ?, timestamp = ? 
                     WHERE user_id = ? AND command_id = ?",
                    [$data['intensity'], $data['volume'], $data['reactionAccuracy'], time(), $data['userId'], $data['commandId']]
                );
            } else {
                $this->db->query(
                    "INSERT INTO mic_results (user_id, command_id, intensity, volume, reaction_accuracy, timestamp) 
                     VALUES (?, ?, ?, ?, ?, ?)",
                    [$data['userId'], $data['commandId'], $data['intensity'], $data['volume'], $data['reactionAccuracy'], time()]
                );
            }

            // Award Points Logic
            $basePoints = 5;
            $accuracyBonus = round($data['reactionAccuracy'] * 0.5);
            $intensityBonus = 0;

            // Get active command
            $stmt = $this->db->query("SELECT command_data FROM commands WHERE is_active = 1 ORDER BY timestamp DESC LIMIT 1");
            $activeCmd = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($activeCmd && isset($activeCmd['command_data'])) {
                $cmdData = json_decode($activeCmd['command_data'], true);
                $targetIntensity = $cmdData['intensity'] ?? 0;
                $userIntensity = (int)$data['intensity'];
                $diff = abs($userIntensity - $targetIntensity);
                $intensityBonus = max(0, 45 - round(($diff / 100) * 45 * 1.5));
            }

            // Check Sim Audience Setting
            $stmt = $this->db->query("SELECT setting_value FROM settings WHERE setting_key = 'sim_audience_on'");
            $simAudienceSetting = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($simAudienceSetting && $simAudienceSetting['setting_value'] === '1') {
                $totalPoints = $basePoints + $accuracyBonus + $intensityBonus;
            } else {
                $totalPoints = $basePoints + $accuracyBonus;
            }

            User::addPoints($data['userId'], $totalPoints);

            // Get new points
            $stmt = $this->db->query("SELECT points FROM users WHERE id = ?", [$data['userId']]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            $newPoints = $user ? (int)$user['points'] : 0;

            $this->jsonResponse(['success' => true, 'points' => $newPoints]);

        } catch (PDOException $e) {
            $this->jsonResponse(['success' => false, 'error' => 'Database error'], 500);
        }
    }
}
