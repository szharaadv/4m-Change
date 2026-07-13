<?php
session_start();
require_once __DIR__ . '/config/paths.php';

if (isset($_SESSION['user'])) {
    header('Location: ' . navLink('modules/dashboard/index.php'));
} else {
    header('Location: ' . navLink('modules/auth/login.php'));
}
exit;