<?php

require_once __DIR__ . '/BaseController.php';
require_once __DIR__ . '/../Services/ValidationService.php';
require_once __DIR__ . '/../Services/CommandService.php';

use App\Services\CommandService;

class AdminController extends BaseController
{   
    private function isAdmin()
    {
        if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
            $_SESSION['error'] = ['Unauthorized access'];
            http_response_code(403);
            $basePath = $this->getBasePath();
            header("Location: {$basePath}/");
            exit;
        }
    }
    
    public function index()
    {
        $this->isAdmin();
        $this->render('admin', data: ['title' => 'Admin Panel', 'user' => $_SESSION]);
    }
    
    public function broadcast()
    {
        $this->isAdmin();
        $type = $_POST['type'] ?? '';
        
        if (empty($type)) {
            $_SESSION['error'] = ['Command type is required'];
            $basePath = $this->getBasePath();
            header("Location: {$basePath}/admin");
            exit;
        }
        
        $commandData = [
            'type' => $type,
            'intensity' => (int)($_POST['intensity'] ?? 50),
            'duration' => (int)($_POST['duration'] ?? 5),
            'countdown' => (int)($_POST['countdown'] ?? 3),
            'message' => $_POST['message'] ?? '',
            'timestamp' => time()
        ];
        
        // Process target categories (combines former groups and tags)
        $categories = [];
        
        if (!empty($_POST['categories'])) {
            $categories = array_map('trim', explode(',', $_POST['categories']));
        }
        
        if (!empty($categories)) {
            $commandData['targetCategories'] = $categories;
        }
        
        // Process target gender
        if (!empty($_POST['gender'])) {
            $commandData['targetGender'] = $_POST['gender'];
        }
        
        try {
            $success = (new CommandService())->broadcastCommand($commandData);
            
            if ($success) {
                $_SESSION['success'] = ['Command broadcast successfully'];
                $_SESSION['last_command'] = $commandData;
            } else {
                $_SESSION['error'] = ['Failed to broadcast command'];
            }
        } catch (Exception $e) {
            $_SESSION['error'] = ['Error broadcasting command: ' . $e->getMessage()];
        }
        
        $basePath = $this->getBasePath();
        header("Location: {$basePath}/admin");
        exit;
    }
}