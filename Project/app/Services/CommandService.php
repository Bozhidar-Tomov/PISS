<?php

namespace App\Services;

require_once __DIR__ . '/../Database/Database.php';
require_once __DIR__ . '/../Models/User.php';

use App\Database\Database;
use App\Models\User;
use PDO;
use PDOException;

class CommandService
{
    private $db;
    
    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function broadcastCommand(array $commandData): bool
    {
        try {
            $this->db->beginTransaction();
            
            $commandData['id'] ??= uniqid('cmd_');
            $commandData['timestamp'] = time();
            
            // Reset all active commands
            $this->db->query("UPDATE commands SET is_active = 0");
            
            // Insert the new command
            $this->db->query(
                "INSERT INTO commands (id, command_type, command_data, is_active, timestamp) 
                 VALUES (?, ?, ?, ?, ?)",
                [
                    $commandData['id'],
                    $commandData['type'] ?? '',
                    json_encode($commandData),
                    true,
                    $commandData['timestamp']
                ]
            );
            
            $this->db->commit();
            return true;
        } catch (PDOException $e) {
            $this->db->rollback();
            return false;
        }
    }

    public function getActiveCommand(): ?array
    {
        try {
            $stmt = $this->db->query(
                "SELECT command_data FROM commands WHERE is_active = 1 ORDER BY timestamp DESC LIMIT 1"
            );
            
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result && isset($result['command_data'])) {
                return json_decode($result['command_data'], true);
            }
            
            return null;
        } catch (PDOException $e) {
            return null;
        }
    }

    public function registerActiveUser(string $userId): bool
    {
        $this->cleanupInactiveUsers();
        
        try {
            $stmt = $this->db->query(
                "SELECT user_id FROM active_users WHERE user_id = ?",
                [$userId]
            );
            
            if ($stmt->rowCount() > 0) {
                // Update existing active user
                $this->db->query(
                    "UPDATE active_users SET last_seen = ? WHERE user_id = ?",
                    [time(), $userId]
                );
            } else {
                // Insert new active user
                $this->db->query(
                    "INSERT INTO active_users (user_id, last_seen) VALUES (?, ?)",
                    [$userId, time()]
                );
            }
            
            return true;
        } catch (PDOException $e) {
            return false;
        }
    }

    public function removeActiveUser(string $userId): bool
    {
        try {
            $this->db->query(
                "DELETE FROM active_users WHERE user_id = ?",
                [$userId]
            );
            
            return true;
        } catch (PDOException $e) {
            return false;
        }
    }

    public function getActiveUsers(): array
    {
        $users = [];
        
        try {
            $stmt = $this->db->query("SELECT * FROM active_users");
            $activeUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($activeUsers as $user) {
                $users[$user['user_id']] = [
                    'lastSeen' => $user['last_seen']
                ];
            }
            
            return $users;
        } catch (PDOException $e) {
            return [];
        }
    }

    public function getActiveUserCount(): int
    {
        $this->cleanupInactiveUsers();
        
        try {
            $stmt = $this->db->query("SELECT COUNT(*) as count FROM active_users");
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return (int)($result['count'] ?? 0);
        } catch (PDOException $e) {
            return 0;
        }
    }

    private function cleanupInactiveUsers(): void
    {
        try {
            $timeout = 60;
            $cutoff = time() - $timeout;
            
            $this->db->query(
                "DELETE FROM active_users WHERE last_seen < ?",
                [$cutoff]
            );
        } catch (PDOException $e) {
            // Silently fail
        }
    }
    
    /**
     * Check if a user matches the target filters for a command
     * 
     * @param string $userId The user ID to check
     * @param array $commandData The command data containing filters
     * @return bool True if the user matches the filters or if no filters are set
     */
    public function userMatchesCommandFilters(string $userId, array $commandData): bool
    {
        // If no filters are set, all users match
        if (empty($commandData['targetCategories']) && 
            empty($commandData['targetGender'])) {
            return true;
        }
        
        try {
            // Get user data
            $stmt = $this->db->query(
                "SELECT * FROM users WHERE id = ?",
                [$userId]
            );
            
            $userData = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$userData) {
                return false;
            }
            
            // Process user categories
            $userCategories = [];
            if (!empty($userData['categories'])) {
                $userCategories = array_map('trim', explode(',', $userData['categories']));
            }
            
            // Check gender filter
            if (!empty($commandData['targetGender']) && $userData['gender'] !== $commandData['targetGender']) {
                return false;
            }
            
            // Check categories filter (AND logic: user must have ALL target categories)
            if (!empty($commandData['targetCategories'])) {
                $targetCategories = is_array($commandData['targetCategories']) 
                    ? $commandData['targetCategories'] 
                    : explode(',', $commandData['targetCategories']);
                $targetCategories = array_map('trim', $targetCategories);
                
                // Check that every target category is in user's categories
                foreach ($targetCategories as $category) {
                    if (!in_array($category, $userCategories)) {
                        return false;
                    }
                }
            }
            
            // User matches all filters
            return true;
        } catch (PDOException $e) {
            // In case of error, default to including the user
            return true;
        }
    }
}
