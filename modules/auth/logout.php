<?php
session_start();
session_destroy();
header('Location: /4m-change/modules/auth/login.php');
exit;
