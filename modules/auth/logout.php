<?php
session_start();
require_once '../../config/database.php';
require_once '../../helpers/audit.php';

// Log before session destroyed
writeAuditLog($pdo, 'LOGOUT', 'User logout');

session_destroy();
header('Location: /4m-change/modules/auth/login.php');
exit;