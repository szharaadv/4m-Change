<?php
define('PROJECT_ROOT', realpath(__DIR__ . '/..'));

function getBaseURL() {
    // Check folder structure to detect environment
    $modulesPath = realpath(__DIR__ . '/../modules');
    
    // If /modules exist in current directory, server DocumentRoot pointing here
    if (is_dir($modulesPath)) {
        // Check if we're at domain root or in subfolder
        $scriptPath = $_SERVER['SCRIPT_NAME']; // e.g., /index.php or /4m-change/index.php
        
        if (strpos($scriptPath, '/4m-change/') !== false) {
            // LOCAL: /4m-change/
            return '/4m-change/';
        } else {
            // SERVER: / (root, karena DocumentRoot sudah di /4m-change/)
            return '/';
        }
    }
    
    // Fallback
    return '/4m-change/';
}

define('BASE_URL', getBaseURL());
define('ASSETS_URL', BASE_URL . 'assets/');

define('CONFIG_DIR', PROJECT_ROOT . '/config');
define('HELPERS_DIR', PROJECT_ROOT . '/helpers');
define('MODULES_DIR', PROJECT_ROOT . '/modules');
define('TEMPLATES_DIR', PROJECT_ROOT . '/templates');
define('ASSETS_DIR', PROJECT_ROOT . '/assets');

function navLink(string $path): string {
    return BASE_URL . ltrim($path, '/');
}