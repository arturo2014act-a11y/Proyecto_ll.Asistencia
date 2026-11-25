<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Evitar caché del navegador en páginas protegidas (RF-03: botón Atrás)
function sendNoCacheHeaders(): void {
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Cache-Control: post-check=0, pre-check=0', false);
    header('Pragma: no-cache');
    header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');
}

function currentUser(): ?array {
    return $_SESSION['user'] ?? null;
}

function isLoggedIn(): bool {
    return isset($_SESSION['user']['id']);
}

function requireLogin(?string $redirectAfter = null): void {
    sendNoCacheHeaders();
    if (!isLoggedIn()) {
        $url = '/sgap/login.php';
        if ($redirectAfter !== null) {
            $url .= '?redirect=' . urlencode($redirectAfter);
        }
        header('Location: ' . $url);
        exit;
    }
}

function requireRole(string $role): void {
    requireLogin();
    $user = currentUser();
    if ($user['role'] !== $role) {
        $target = $user['role'] === 'profesor' ? '/sgap/profesor/dashboard.php' : '/sgap/estudiante/dashboard.php';
        header('Location: ' . $target);
        exit;
    }
}

function e(string $str): string {
    return htmlspecialchars($str, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
