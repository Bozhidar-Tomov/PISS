<?php
class LogoutController
{
    public function logout()
    {
        session_unset();
        session_destroy();
        session_abort();
        
        $basePath = defined('BASE_PATH') ? BASE_PATH : '';
        header("Location: {$basePath}/login");
        exit;
    }
}
