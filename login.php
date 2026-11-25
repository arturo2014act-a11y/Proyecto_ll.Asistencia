<?php
declare(strict_types=1);
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';

$redirect = $_GET['redirect'] ?? $_POST['redirect'] ?? '';

if (isLoggedIn()) {
    if ($redirect !== '' && str_starts_with($redirect, '/sgap/')) {
        header('Location: ' . $redirect);
        exit;
    }
    header('Location: /sgap/index.php');
    exit;
}

$error = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $error = 'Correo o contraseña incorrectos';
    } else {
        $pdo = getDB();
        $stmt = $pdo->prepare('SELECT id, full_name, email, password_hash, role FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['user'] = [
                'id' => (int)$user['id'],
                'full_name' => $user['full_name'],
                'email' => $user['email'],
                'role' => $user['role'],
            ];
            if ($redirect !== '' && str_starts_with($redirect, '/sgap/')) {
                header('Location: ' . $redirect);
                exit;
            }
            $target = $user['role'] === 'profesor' ? '/sgap/profesor/dashboard.php' : '/sgap/estudiante/dashboard.php';
            header('Location: ' . $target);
            exit;
        } else {
            $error = 'Correo o contraseña incorrectos';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Iniciar sesión - SGAP</title>
  <link rel="stylesheet" href="/sgap/assets/css/style.css">
</head>
<body>
  <div class="auth-container">
    <div class="card">
      <h1>Iniciar sesión</h1>
      <p class="muted" style="margin-bottom:1.2rem;">Accede a SGAP con tu correo y contraseña</p>
      <?php if (isset($_GET['registered'])): ?>
        <div class="alert alert-success">Cuenta registrada correctamente. Ahora puedes iniciar sesión.</div>
      <?php endif; ?>
      <?php if ($error !== ''): ?>
        <div class="alert alert-danger"><?= e($error) ?></div>
      <?php endif; ?>
      <form method="POST" novalidate>
        <input type="hidden" name="redirect" value="<?= e($redirect) ?>">
        <div class="form-group">
          <label for="email">Correo electrónico</label>
          <input type="email" id="email" name="email" class="form-control" value="<?= e($email) ?>" required>
        </div>
        <div class="form-group">
          <label for="password">Contraseña</label>
          <input type="password" id="password" name="password" class="form-control" required>
        </div>
        <button type="submit" class="btn btn-primary" style="width:100%;">Entrar</button>
      </form>
      <p class="muted" style="margin-top:1rem; text-align:center;">
        ¿No tienes cuenta? <a href="/sgap/register.php">Regístrate aquí</a>
      </p>
    </div>
  </div>
</body>
</html>
