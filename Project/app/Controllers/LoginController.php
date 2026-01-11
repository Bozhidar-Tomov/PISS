<?php
require_once __DIR__ . '/BaseController.php';
require_once __DIR__ . '/../Models/User.php';
require_once __DIR__ . '/../Services/ValidationService.php';

use App\Models\User;
use App\Services\ValidationService;

class LoginController extends BaseController
{
    public function showForm()
    {
        if (!empty($_SESSION['user'])) {
            $_SESSION['warning'] = ['You are already logged in.'];
            $basePath = $this->getBasePath();
            header("Location: {$basePath}/", true, 303);
            exit;
        }

        $this->render('login', data: ['title' => 'Login']);
    }

    public function handleLogin()
    {
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';
        $errors = [];

        // TODO: Uncomment to enable validation
        // $errors = ValidationService::validateLogin(['username' => $username, 'password' => $password]);

        if (empty($errors)) {
            try {
                $user = User::findByUsername($username);

                if (!$user) {
                    $errors[] = 'Invalid username or password.';
                } 
                else if (password_verify($password, $user->password)) {
                    $_SESSION['user'] = [
                        'id' => (string) $user->id,
                        'username' => htmlspecialchars($user->username, ENT_QUOTES, 'UTF-8'),
                        'role' => $user->role ?? '',
                        'points' => $user->points ?? 0,
                        'groups' => $user->groups ?? [],
                        'tags' => $user->tags ?? [],
                        'gender' => $user->gender ?? '',
                        'title' => $user->role === 'admin' ? 'Admin Panel' : 'Room View'
                    ];

                    $basePath = $this->getBasePath();
                    $redirectPath = $user->role === 'admin' ? "{$basePath}/admin" : "{$basePath}/room";
                    header("Location: {$redirectPath}");
                    exit;
                }
                else {
                    $errors[] = 'Invalid username or password.';
                }
            } catch (Throwable $e) {
                $errors[] = 'Database error: ' . $e->getMessage();
            }
        }

        $this->render('login', data: ['errors' => $errors, 'title' => 'Login']);
    }
}
