<?php
// ============================================================
// DYNAMIC BASE URL DETECTION
// ============================================================

define('PROJECT_ROOT', realpath(__DIR__ . '/..'));

function getBaseURL() {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'];
    
    // Detect environment by domain
    if (strpos($host, 'localhost') !== false || strpos($host, '127.0.0.1') !== false) {
        // LOCAL XAMPP: /4m-change/
        return '/4m-change/';
    } else if (strpos($host, 'yadin.com') !== false) {
        // PRODUCTION SERVER: deployed at domain root, so BASE_URL = /
        return '/';
    } else {
        // DEFAULT: try to detect from script path
        $scriptPath = $_SERVER['SCRIPT_NAME'];
        $scriptDir = dirname($scriptPath);
        
        // If script path contains project folder, use it
        if (strpos($scriptDir, '/4m-change') !== false) {
            return '/4m-change/';
        }
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