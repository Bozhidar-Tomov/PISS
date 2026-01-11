<?php
session_start();
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user']) || empty($_SESSION['user']['id'])) {
    echo json_encode([
        'success' => false,
        'error' => 'Unauthorized access'
    ]);
    exit;
}

// Check if request is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'error' => 'Invalid request method'
    ]);
    exit;
}

// Get the user ID from session
$userId = $_SESSION['user']['id'];

// Get categories from POST data
$categoriesInput = $_POST['categories'] ?? '';
$categories = array_filter(array_map('trim', explode(',', $categoriesInput)));

// Validate categories
if (empty($categories)) {
    echo json_encode([
        'success' => false,
        'error' => 'Please provide at least one category'
    ]);
    exit;
}

// Update user categories in database
require_once __DIR__ . '/../../app/Models/User.php';
require_once __DIR__ . '/../../app/Database/Database.php';

try {
    // Get current user data
    $db = \App\Database\Database::getInstance();
    $stmt = $db->query("SELECT * FROM users WHERE id = ?", [$userId]);
    $userData = $stmt->fetch(\PDO::FETCH_ASSOC);
    
    if (!$userData) {
        echo json_encode([
            'success' => false,
            'error' => 'User not found'
        ]);
        exit;
    }
    
    // Create user object
    $user = new \App\Models\User($userData);
    
    // Update categories
    $user->categories = $categories;
    
    // Save user
    if ($user->save()) {
        // Update session data
        $_SESSION['user']['categories'] = $categories;
        
        echo json_encode([
            'success' => true,
            'message' => 'Categories updated successfully',
            'categories' => $categories
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'error' => 'Failed to update categories'
        ]);
    }
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'An error occurred: ' . $e->getMessage()
    ]);
} 