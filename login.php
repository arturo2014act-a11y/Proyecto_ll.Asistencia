<?php
declare(strict_types=1);

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/validators.php';
require_once __DIR__ . '/includes/layout.php';

if (isLoggedIn()) {
    $u = currentUser();
    $target = $u['role'] === 'profesor' ? '/sgap/profesor/dashboard.php' : '/sgap/estudiante/dashboard.php';
    header('Location: ' . $target);
    exit;
}

$errors = [];
$generalError = '';
$email = '';
$redirect = $_GET['redirect'] ?? $_POST['redirect'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim((string)($_POST['email'] ?? ''));
    $password = (string)($_POST['password'] ?? '');

    if ($email === '') {
        $errors['email'] = 'Este campo es obligatorio';
    } elseif (($emailErr = validateEmail($email)) !== null) {
        $errors['email'] = $emailErr;
    }

    if ($password === '') {
        $errors['password'] = 'Este campo es obligatorio';
    }

    if (empty($errors)) {
        $db = getDB();
        $stmt = $db->prepare('SELECT id, full_name, email, password_hash, role, control_number, failed_attempts, locked_until FROM users WHERE LOWER(email) = LOWER(?) LIMIT 1');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && $user['locked_until'] !== null && strtotime($user['locked_until']) > time()) {
            $generalError = 'Demasiados intentos. Intenta nuevamente en 5 minutos';
        } elseif ($user && password_verify($password, $user['password_hash'])) {
            $db->prepare('UPDATE users SET failed_attempts = 0, locked_until = NULL WHERE id = ?')->execute([$user['id']]);
            session_regenerate_id(true);
            $_SESSION['user'] = [
                'id' => (int)$user['id'],
                'full_name' => $user['full_name'],
                'email' => $user['email'],
                'role' => $user['role'],
                'control_number' => $user['control_number'],
            ];
            if ($redirect !== '' && str_starts_with($redirect, '/sgap/')) {
                header('Location: ' . $redirect);
                exit;
            }
            $target = $user['role'] === 'profesor' ? '/sgap/profesor/dashboard.php' : '/sgap/estudiante/dashboard.php';
            header('Location: ' . $target);
            exit;
        } else {
            if ($user) {
                $attempts = (int)$user['failed_attempts'] + 1;
                if ($attempts >= 5) {
                    $lockedUntil = date('Y-m-d H:i:s', time() + 300);
                    $db->prepare('UPDATE users SET failed_attempts = ?, locked_until = ? WHERE id = ?')->execute([$attempts, $lockedUntil, $user['id']]);
                    $generalError = 'Demasiados intentos. Intenta nuevamente en 5 minutos';
                } else {
                    $db->prepare('UPDATE users SET failed_attempts = ? WHERE id = ?')->execute([$attempts, $user['id']]);
                    $generalError = 'Correo o contraseña incorrectos';
                }
            } else {
                $generalError = 'Correo o contraseña incorrectos';
            }
        }
    }
}

renderHeader('Iniciar sesión');
?>
<div class="card">
  <h1>Iniciar sesión - SGAP</h1>
  <?php if ($generalError !== ''): ?>
    <div class="alert alert-error"><?= e($generalError) ?></div>
  <?php endif; ?>
  <form method="POST" action="/sgap/login.php" novalidate>
    <input type="hidden" name="redirect" value="<?= e($redirect) ?>">
    <div class="form-group">
      <label for="email">Correo electrónico <span class="required-mark">*</span></label>
      <input type="email" id="email" name="email" value="<?= e($email) ?>" maxlength="100" required>
      <?php if (isset($errors['email'])): ?>
        <div class="field-error"><?= e($errors['email']) ?></div>
      <?php endif; ?>
    </div>
    <div class="form-group">
      <label for="password">Contraseña <span class="required-mark">*</span></label>
      <div class="password-wrapper">
        <input type="password" id="password" name="password" required>
        <button type="button" class="toggle-password" onclick="const p=document.getElementById('password'); const isP=p.type==='password'; p.type=isP?'text':'password'; this.textContent=isP?'Ocultar':'Mostrar';">Mostrar</button>
      </div>
      <?php if (isset($errors['password'])): ?>
        <div class="field-error"><?= e($errors['password']) ?></div>
      <?php endif; ?>
    </div>
    <button type="submit" class="btn btn-primary" style="width:100%;">Iniciar sesión</button>
  </form>
</div>
<?php renderFooter(); ?>
