<?php
session_start();

require_once __DIR__ . '/../routes/Router.php';

// Enhanced base path detection that works in any directory structure
$documentRoot = str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT']));
$currentPath = str_replace('\\', '/', realpath(__DIR__));
$scriptName = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));

// First attempt: Use the difference between current path and document root
$relativePath = str_replace($documentRoot, '', $currentPath);

// Second attempt: If first method doesn't work well (e.g., symbolic links), use SCRIPT_NAME
if (empty($relativePath) || $relativePath === $currentPath) {
    $relativePath = $scriptName;
}

// Ensure proper format (leading slash, no trailing slash)
$basePath = '/' . trim($relativePath, '/');
$basePath = $basePath === '/' ? '' : $basePath;

// Create router with detected base path
$router = new Router($basePath);
require_once __DIR__ . '/../routes/routes.php';

// Make the base path available for the application
define('BASE_PATH', $basePath);

$router->dispatch($_SERVER['REQUEST_URI'], $_SERVER['REQUEST_METHOD']);
