<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';

function renderHeader(string $title, string $activeNav = ''): void {
    $user = currentUser();
    $role = $user['role'] ?? '';
    $name = $user['full_name'] ?? '';
    ?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e($title) ?> - SGAP</title>
  <link rel="stylesheet" href="/sgap/assets/css/style.css">
</head>
<body>
<?php if ($user !== null): ?>
  <div class="mobile-topbar">
    <span class="sidebar-brand">SGAP</span>
    <button type="button" class="menu-toggle" onclick="document.getElementById('sidebarMenu').classList.toggle('open')">Menú</button>
  </div>
  <div class="app-shell">
    <aside id="sidebarMenu" class="sidebar">
      <div class="sidebar-header">
        <div class="sidebar-brand">SGAP</div>
        <div class="sidebar-user"><?= e($name) ?> (<?= $role === 'profesor' ? 'Profesor' : 'Estudiante' ?>)</div>
      </div>
      <nav class="sidebar-nav">
        <?php if ($role === 'profesor'): ?>
          <a href="/sgap/profesor/dashboard.php" class="<?= $activeNav === 'inicio' ? 'active' : '' ?>">Inicio</a>
          <a href="/sgap/profesor/courses.php" class="<?= $activeNav === 'cursos' ? 'active' : '' ?>">Mis cursos</a>
          <a href="/sgap/profesor/reports.php" class="<?= $activeNav === 'reportes' ? 'active' : '' ?>">Reportes</a>
        <?php else: ?>
          <a href="/sgap/estudiante/dashboard.php" class="<?= $activeNav === 'inicio' ? 'active' : '' ?>">Inicio</a>
          <a href="/sgap/estudiante/dashboard.php#cursos" class="<?= $activeNav === 'cursos' ? 'active' : '' ?>">Mis cursos</a>
          <a href="/sgap/qr_checkin.php" class="<?= $activeNav === 'qr' ? 'active' : '' ?>">Registrar asistencia</a>
          <a href="/sgap/estudiante/history.php" class="<?= $activeNav === 'historial' ? 'active' : '' ?>">Historial</a>
        <?php endif; ?>
        <a href="/sgap/logout.php" class="logout-link">Cerrar sesión</a>
      </nav>
    </aside>
    <main class="main-content">
<?php else: ?>
  <main class="auth-container">
<?php endif;
}

function renderFooter(): void {
    ?>
  </main>
<?php if (currentUser() !== null): ?>
  </div>
<?php endif; ?>
</body>
</html>
    <?php
}
