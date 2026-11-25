<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

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
    requireLogin($_SERVER['REQUEST_URI'] ?? null);
    $user = currentUser();
    if ($user['role'] !== $role) {
        http_response_code(403);
        $home = $user['role'] === 'profesor' ? '/sgap/profesor/dashboard.php' : '/sgap/estudiante/dashboard.php';
        require_once __DIR__ . '/layout.php';
        renderHeader('Acceso denegado');
        echo '<div class="card">';
        echo '<h1>Acceso denegado</h1>';
        echo '<div class="alert alert-error">No tienes permiso para realizar esta acción</div>';
        echo '<a class="btn btn-primary" href="' . e($home) . '">Volver al inicio</a>';
        echo '</div>';
        renderFooter();
        exit;
    }
}

function e(string $str): string {
    return htmlspecialchars($str, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
