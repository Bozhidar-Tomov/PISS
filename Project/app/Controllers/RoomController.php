<?php
require_once __DIR__ . '/BaseController.php';

class RoomController extends BaseController
{
    public function showRoom()
    {
        if (!isset($_SESSION['user'])) {
            $_SESSION['error'] = ['You must be logged in to access this page.'];
            $basePath = $this->getBasePath();
            header("Location: {$basePath}/");
            exit;
        }
        $user = $_SESSION['user'];
        // Load latest points from users.json
        require_once __DIR__ . '/../Models/User.php';
        $users = \App\Models\User::loadUsers();
        $points = isset($users[$user['id']]) ? $users[$user['id']]['points'] : ($user['points'] ?? 0);
        
        // Get categories from user data
        $categories = [];
        if (isset($user['categories']) && is_array($user['categories'])) {
            $categories = $user['categories'];
        } elseif (isset($users[$user['id']]['categories'])) {
            $categories = $users[$user['id']]['categories'];
        }
        
        // Format categories for display
        $categoriesStr = !empty($categories) ? implode(', ', $categories) : 'None';
        
        $roomData = [
            'title' => 'Room View',
            'id' => $user['id'],
            'username' => htmlspecialchars($user['username']),
            'role' => $user['role'] ?? '',
            'points' => $points,
            'groups' => $user['groups'] ?? [],
            'tags' => $user['tags'] ?? [],
            'categories' => $categories,
            'categoriesStr' => $categoriesStr,
            'gender' => $user['gender'] ?? '',
        ];
        $this->render('room', $roomData);
    }
} 