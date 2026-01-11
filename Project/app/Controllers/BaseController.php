<?php

class BaseController
{
    protected function render($view, $data = [])
    {
        $viewFile = __DIR__ . '/../Views/' . $view . '.php';
        $basePath = defined('BASE_PATH') ? BASE_PATH : '';

        ob_start();
        if (file_exists($viewFile)) {
            $viewName = str_contains($view, '/') ? substr($view, 0, strpos($view, '/')) : $view;
            $viewStyle = $this->assetPath("css/views/{$viewName}.css");
            extract($data);
            require $viewFile;
        } else {
            $view = '404';
            $viewFile = __DIR__ . '/../Views/404.php';
            $viewStyle = $this->assetPath("css/views/404.css");
            require_once $viewFile;
        }
        $content = ob_get_clean();
        require __DIR__ . '/../Views/layout.php';
    }
    
    protected function getBasePath()
    {
        return defined('BASE_PATH') ? BASE_PATH : '';
    }
    
    // Helper method to generate correct paths for assets
    protected function assetPath($path)
    {
        $basePath = $this->getBasePath();
        // Remove any leading/trailing slashes from the path and join with a single slash
        return $basePath . '/' . ltrim(trim($path, '/'), '/');
    }
    
    // Helper method to generate correct URLs for links
    protected function url($path = '')
    {
        $basePath = $this->getBasePath();
        // If path is empty, return the base path
        if (empty($path)) {
            return $basePath ?: '/';
        }
        // Otherwise, join base path and path with a single slash
        return $basePath . '/' . ltrim(trim($path, '/'), '/');
    }
    
    // Helper to inject base path for JavaScript
    protected function getBasePathScript()
    {
        $basePath = $this->getBasePath();
        return "<script>window.basePath = \"{$basePath}\";</script>";
    }
}
