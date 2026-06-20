<?php
define('PROJECT_ROOT', realpath(__DIR__ . '/..'));

function getBaseURL() {
    // Detect by domain
    if (strpos($_SERVER['HTTP_HOST'], 'yadin.com') !== false) {
        // PRODUCTION: /4m-change/ (karena domain pointing ke parent htdocs)
        return '/4m-change/';
    } else {
        // LOCAL: /4m-change/
        return '/4m-change/';
    }
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