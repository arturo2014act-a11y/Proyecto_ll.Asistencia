<?php
declare(strict_types=1);
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/validators.php';

if (isLoggedIn()) {
    header('Location: /sgap/index.php');
    exit;
}

$errors = [];
$fullName = '';
$email = '';
$role = 'estudiante';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? '';

    if ($err = validateFullName($fullName)) {
        $errors['full_name'] = $err;
    }
    if ($err = validateEmail($email)) {
        $errors['email'] = $err;
    }
    if ($err = validatePassword($password)) {
        $errors['password'] = $err;
    }
    if (!in_array($role, ['profesor', 'estudiante'], true)) {
        $errors['role'] = 'Selecciona un rol válido (Profesor o Estudiante).';
    }

    if (empty($errors)) {
        $pdo = getDB();
        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $errors['email'] = 'El correo ya se encuentra registrado';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $ins = $pdo->prepare('INSERT INTO users (full_name, email, password_hash, role) VALUES (?, ?, ?, ?)');
            $ins->execute([$fullName, $email, $hash, $role]);
            header('Location: /sgap/login.php?registered=1');
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Registro - SGAP</title>
  <link rel="stylesheet" href="/sgap/assets/css/style.css">
</head>
<body>
  <div class="auth-container">
    <div class="card">
      <h1>Crear cuenta en SGAP</h1>
      <p class="muted" style="margin-bottom:1.2rem;">Sistema de Gestión de Asistencias y Participación</p>
      <form method="POST" novalidate>
        <div class="form-group">
          <label for="full_name">Nombre completo</label>
          <input type="text" id="full_name" name="full_name" class="form-control <?= isset($errors['full_name']) ? 'is-invalid' : '' ?>" value="<?= e($fullName) ?>" required>
          <?php if (isset($errors['full_name'])): ?><div class="field-error"><?= e($errors['full_name']) ?></div><?php endif; ?>
        </div>
        <div class="form-group">
          <label for="email">Correo electrónico</label>
          <input type="email" id="email" name="email" class="form-control <?= isset($errors['email']) ? 'is-invalid' : '' ?>" value="<?= e($email) ?>" required>
          <?php if (isset($errors['email'])): ?><div class="field-error"><?= e($errors['email']) ?></div><?php endif; ?>
        </div>
        <div class="form-group">
          <label for="password">Contraseña</label>
          <input type="password" id="password" name="password" class="form-control <?= isset($errors['password']) ? 'is-invalid' : '' ?>" required>
          <?php if (isset($errors['password'])): ?><div class="field-error"><?= e($errors['password']) ?></div><?php endif; ?>
          <small class="muted">Mínimo 8 caracteres, 1 mayúscula, 1 minúscula, 1 número, sin espacios.</small>
        </div>
        <div class="form-group">
          <label for="role">Rol</label>
          <select id="role" name="role" class="form-control <?= isset($errors['role']) ? 'is-invalid' : '' ?>">
            <option value="estudiante" <?= $role === 'estudiante' ? 'selected' : '' ?>>Estudiante</option>
            <option value="profesor" <?= $role === 'profesor' ? 'selected' : '' ?>>Profesor</option>
          </select>
          <?php if (isset($errors['role'])): ?><div class="field-error"><?= e($errors['role']) ?></div><?php endif; ?>
        </div>
        <button type="submit" class="btn btn-primary" style="width:100%;">Registrarse</button>
      </form>
      <p class="muted" style="margin-top:1rem; text-align:center;">
        ¿Ya tienes cuenta? <a href="/sgap/login.php">Inicia sesión</a>
      </p>
    </div>
  </div>
</body>
</html>
