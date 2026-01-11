<?php

namespace App\Models;
require_once __DIR__ . '/../Database/Database.php';

use App\Database\Database;
use PDO;
use PDOException;

class User
{
    public $id;
    public $username;
    public $role; // participant, viewer, leader, admin
    public $categories = [];
    public $points = 0;
    public $gender;
    public $password;
    public $created_at;

    private $db;

    public function __construct($data = [])
    {
        $this->db = Database::getInstance();
        
        foreach ($data as $key => $value) {
            if (property_exists($this, $key)) {
                $this->$key = $value;
            }
        }
        
        // Handle legacy data format conversion
        if (isset($data['groups']) || isset($data['tags'])) {
            $this->categories = [];
            
            // Add groups to categories
            if (isset($data['groups']) && is_array($data['groups'])) {
                foreach ($data['groups'] as $group) {
                    if (!empty($group) && !in_array($group, $this->categories)) {
                        $this->categories[] = $group;
                    }
                }
            }
            
            // Add tags to categories
            if (isset($data['tags']) && is_array($data['tags'])) {
                foreach ($data['tags'] as $tag) {
                    if (!empty($tag) && !in_array($tag, $this->categories)) {
                        $this->categories[] = $tag;
                    }
                }
            }
        }
    }

    public function save(): bool
    {
        try {
            $this->db->beginTransaction();
            
            if (!$this->created_at) {
                $this->created_at = date('Y-m-d H:i:s');
            }
            
            if (!$this->id) {
                $this->id = uniqid();
            }
            
            // Convert categories array to string
            $categoriesStr = !empty($this->categories) ? implode(',', $this->categories) : null;
            
            // Check if user exists
            $stmt = $this->db->query("SELECT id FROM users WHERE id = ?", [$this->id]);
            $exists = $stmt->rowCount() > 0;
            
            if ($exists) {
                // Update existing user
                $this->db->query(
                    "UPDATE users SET username = ?, role = ?, points = ?, gender = ?, password = ?, categories = ? WHERE id = ?",
                    [
                        $this->username,
                        $this->role,
                        $this->points,
                        $this->gender,
                        $this->password,
                        $categoriesStr,
                        $this->id
                    ]
                );
            } else {
                // Insert new user
                $this->db->query(
                    "INSERT INTO users (id, username, role, points, gender, password, categories, created_at) 
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
                    [
                        $this->id,
                        $this->username,
                        $this->role,
                        $this->points,
                        $this->gender,
                        $this->password,
                        $categoriesStr,
                        $this->created_at
                    ]
                );
            }
            
            $this->db->commit();
            return true;
        } catch (PDOException $e) {
            $this->db->rollback();
            $_SESSION['error'] = "User save failed: " . $e->getMessage();
            return false;
        }
    }

    public static function findByUsername($username)
    {
        $db = Database::getInstance();
        
        $stmt = $db->query(
            "SELECT * FROM users WHERE username = ?",
            [$username]
        );
        
        $userData = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$userData) {
            return null;
        }
        
        $user = new self();
        $user->id = $userData['id'];
        $user->username = $userData['username'];
        $user->role = $userData['role'] ?? 'participant';
        $user->points = (int)$userData['points'] ?? 0;
        $user->gender = $userData['gender'];
        $user->password = $userData['password'];
        $user->created_at = $userData['created_at'];
        
        // Process categories
        $user->categories = [];
        if (!empty($userData['categories'])) {
            $user->categories = explode(',', $userData['categories']);
        }
        
        return $user;
    }
    
    public static function loadUsers(): array
    {
        $db = Database::getInstance();
        $users = [];
        
        $stmt = $db->query("SELECT * FROM users ORDER BY created_at");
        $userRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($userRows as $userData) {
            $userId = $userData['id'];
            
            // Process categories
            $categories = [];
            if (!empty($userData['categories'])) {
                $categories = explode(',', $userData['categories']);
            }
            
            $users[$userId] = [
                'username' => $userData['username'],
                'role' => $userData['role'],
                'categories' => $categories,
                'points' => (int)$userData['points'],
                'gender' => $userData['gender'],
                'password' => $userData['password'],
                'created_at' => $userData['created_at']
            ];
        }
        
        return $users;
    }
    
    public static function addPoints($userId, $pointsToAdd) {
        $db = Database::getInstance();
        
        try {
            $db->query(
                "UPDATE users SET points = points + ? WHERE id = ?",
                [(int)$pointsToAdd, $userId]
            );
            
            return true;
        } catch (PDOException $e) {
            return false;
        }
    }

    public static function transferPoints($fromUserId, $toUsername, $amount, $message = '') {
        $db = Database::getInstance();
        $amount = (int)$amount;
        
        try {
            $db->beginTransaction();
            
            // Check if sender exists and has enough points
            $stmt = $db->query(
                "SELECT id, points, username FROM users WHERE id = ?",
                [$fromUserId]
            );
            $sender = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$sender) {
                return ['success' => false, 'error' => 'Sender not found'];
            }
            
            if ($sender['points'] < $amount) {
                return ['success' => false, 'error' => 'Insufficient points'];
            }
            
            // Find recipient by username
            $stmt = $db->query(
                "SELECT id FROM users WHERE LOWER(username) = LOWER(?)",
                [$toUsername]
            );
            $recipient = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$recipient) {
                return ['success' => false, 'error' => 'Recipient not found'];
            }
            
            $toUserId = $recipient['id'];
            
            // Update sender's points
            $db->query(
                "UPDATE users SET points = points - ? WHERE id = ?",
                [$amount, $fromUserId]
            );
            
            // Update recipient's points
            $db->query(
                "UPDATE users SET points = points + ? WHERE id = ?",
                [$amount, $toUserId]
            );
            
            // Log the transfer with message
            $db->query(
                "INSERT INTO point_transfers (from_user_id, to_user_id, amount, message, timestamp) 
                 VALUES (?, ?, ?, ?, ?)",
                [$fromUserId, $toUserId, $amount, $message, time()]
            );
            
            // Get the transfer ID
            $transferId = $db->lastInsertId();
            
            // Get updated points for sender
            $stmt = $db->query(
                "SELECT points FROM users WHERE id = ?",
                [$fromUserId]
            );
            $updatedSender = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $db->commit();
            
            return [
                'success' => true, 
                'points' => (int)$updatedSender['points'],
                'transferId' => $transferId,
                'fromUsername' => $sender['username'],
                'toUserId' => $toUserId
            ];
        } catch (PDOException $e) {
            $db->rollback();
            return ['success' => false, 'error' => 'Transfer failed'];
        }
    }
}
