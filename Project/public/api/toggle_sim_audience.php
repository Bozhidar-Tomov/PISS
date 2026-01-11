<?php
require_once __DIR__ . '/../../app/Database/Database.php';
use App\Database\Database;

header('Content-Type: application/json');
$db = Database::getInstance();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['sim_audience'])) {
            $db->query(
                "INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) 
                 ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)",
                ['sim_audience_on', '1']
            );
            echo json_encode(['success' => true, 'enabled' => true]);
            exit;
        } else {
            $db->query(
                "INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) 
                 ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)",
                ['sim_audience_on', '0']
            );
            echo json_encode(['success' => true, 'enabled' => false]);
            exit;
        }
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $stmt = $db->query(
            "SELECT setting_value FROM settings WHERE setting_key = ?",
            ['sim_audience_on']
        );
        $setting = $stmt->fetch(PDO::FETCH_ASSOC);
        $enabled = $setting && $setting['setting_value'] === '1';
        
        echo json_encode(['success' => true, 'enabled' => $enabled]);
        exit;
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
        exit;
    }
}

echo json_encode(['success' => false, 'error' => 'Invalid request']); 