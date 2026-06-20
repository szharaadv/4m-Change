<?php
define('PROJECT_ROOT', realpath(__DIR__ . '/..'));

// ============================================================
// DETECT ENVIRONMENT & SET BASE_URL ACCORDINGLY
// ============================================================

function getBaseURL() {
    $host = $_SERVER['HTTP_HOST'] ?? '';
    
    // If accessing from server domain
    if (stripos($host, 'yadin.com') !== false) {
        // SERVER: Base URL = root (/)
        return '/';
    }
    
    // Otherwise LOCAL: Base URL = /4m-change/
    return '/4m-change/';
}

define('BASE_URL', getBaseURL());
define('ASSETS_URL', BASE_URL . 'assets/');

// Paths
define('CONFIG_DIR', PROJECT_ROOT . '/config');
define('HELPERS_DIR', PROJECT_ROOT . '/helpers');
define('MODULES_DIR', PROJECT_ROOT . '/modules');
define('TEMPLATES_DIR', PROJECT_ROOT . '/templates');
define('ASSETS_DIR', PROJECT_ROOT . '/assets');

function navLink(string $path): string {
    return BASE_URL . ltrim($path, '/');
}