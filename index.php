<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';

if (isLoggedIn()) {
    $role = currentUser()['role'];
    header('Location: ' . ($role === 'profesor' ? '/sgap/profesor/dashboard.php' : '/sgap/estudiante/dashboard.php'));
    exit;
}
header('Location: /sgap/login.php');
exit;
