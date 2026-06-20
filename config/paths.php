<?php
// ============================================================
// DYNAMIC BASE URL DETECTION
// ============================================================

define('PROJECT_ROOT', realpath(__DIR__ . '/..'));

// Auto-detect base URL based on current server
function getBaseURL() {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'];
    
    // Get script path
    $scriptPath = $_SERVER['SCRIPT_NAME']; // e.g., /4m-change/index.php
    $scriptDir = dirname($scriptPath);     // e.g., /4m-change
    
    // Detect environment
    if (strpos($host, 'localhost') !== false || strpos($host, '127.0.0.1') !== false) {
        // LOCAL: keep /4m-change/ in path
        return $scriptDir . '/';
    } else {
        // SERVER: assume deployed at root
        // https://4m-change.yadin.com/ → BASE_URL = /
        return '/';
    }
}

define('BASE_URL', getBaseURL());
define('ASSETS_URL', BASE_URL . 'assets/');

// Define common paths
define('CONFIG_DIR', PROJECT_ROOT . '/config');
define('HELPERS_DIR', PROJECT_ROOT . '/helpers');
define('MODULES_DIR', PROJECT_ROOT . '/modules');
define('TEMPLATES_DIR', PROJECT_ROOT . '/templates');
define('ASSETS_DIR', PROJECT_ROOT . '/assets');

// Navigation helper
function navLink(string $path): string {
    return BASE_URL . ltrim($path, '/');
}