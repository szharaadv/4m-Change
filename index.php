<?php
session_start();
if (isset($_SESSION['user'])) {
    header('Location: /4m-change/modules/dashboard/index.php');
} else {
    header('Location: /4m-change/modules/auth/login.php');
}
exit;
