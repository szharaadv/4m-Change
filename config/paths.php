<?php
define('PROJECT_ROOT', realpath(__DIR__ . '/..'));

// ============================================================
// HARDCODED untuk masing-masing environment
// ============================================================

// LOCAL: /4m-change/
// SERVER: /
if (getenv('APP_ENV') === 'production' || strpos($_SERVER['HTTP_HOST'], 'yadin.com') !== false) {
    define('BASE_URL', '/');
} else {
    define('BASE_URL', '/4m-change/');
}

define('ASSETS_URL', BASE_URL . 'assets/');

define('CONFIG_DIR', PROJECT_ROOT . '/config');
define('HELPERS_DIR', PROJECT_ROOT . '/helpers');
define('MODULES_DIR', PROJECT_ROOT . '/modules');
define('TEMPLATES_DIR', PROJECT_ROOT . '/templates');
define('ASSETS_DIR', PROJECT_ROOT . '/assets');

function navLink(string $path): string {
    return BASE_URL . ltrim($path, '/');
}