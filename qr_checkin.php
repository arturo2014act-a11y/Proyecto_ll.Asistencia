<?php
declare(strict_types=1);

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/layout.php';

requireRole('estudiante');
$user = currentUser();
$db = getDB();

$token = trim((string)($_GET['token'] ?? $_POST['token'] ?? ''));
// Si el estudiante pegó la URL completa, extraer el parámetro token
if ($token !== '' && str_contains($token, 'token=')) {
    parse_str((string)parse_url($token, PHP_URL_QUERY), $q);
    if (!empty($q['token'])) {
        $token = trim((string)$q['token']);
    }
}

$statusMsg = '';
$isSuccess = false;

if ($token !== '') {
    $sStmt = $db->prepare('
        SELECT s.*, c.name AS course_name, c.group_name
        FROM attendance_sessions s
        JOIN courses c ON c.id = s.course_id
        WHERE s.qr_token = ?
        LIMIT 1
    ');
    $sStmt->execute([$token]);
    $session = $sStmt->fetch();

    if (!$session) {
        $statusMsg = 'QR vencido o inválido';
    } elseif ($session['status'] === 'cerrada') {
        $statusMsg = 'La clase ya finalizó';
    } elseif ($session['qr_expires_at'] === null || strtotime($session['qr_expires_at']) < time()) {
        $statusMsg = 'QR vencido o inválido';
    } else {
        $recStmt = $db->prepare('SELECT id, status FROM attendance_records WHERE session_id = ? AND student_id = ?');
        $recStmt->execute([$session['id'], $user['id']]);
        $rec = $recStmt->fetch();

        if (!$rec) {
            $statusMsg = 'No estás inscrito en este curso';
        } elseif ($rec['status'] === 'Presente') {
            $statusMsg = 'Asistencia ya registrada';
            $isSuccess = true;
        } else {
            $db->prepare('UPDATE attendance_records SET status = "Presente" WHERE id = ?')->execute([$rec['id']]);
            $statusMsg = 'Asistencia registrada';
            $isSuccess = true;
        }
    }
}

renderHeader('Registrar asistencia', 'qr');
?>
<h1>Registrar asistencia por QR</h1>

<?php if ($statusMsg !== ''): ?>
  <div class="alert <?= $isSuccess ? 'alert-success' : 'alert-error' ?>"><?= e($statusMsg) ?></div>
<?php endif; ?>

<div class="card">
  <h2>Ingresar enlace o código QR</h2>
  <p style="margin-bottom:12px;">Escanea el código QR con la cámara de tu celular o pega el enlace/código aquí. Si no hay cámara disponible, solicita al profesor el registro manual.</p>
  <form method="GET" action="/sgap/qr_checkin.php">
    <div class="form-group">
      <label for="token">Enlace o token del QR <span class="required-mark">*</span></label>
      <input type="text" id="token" name="token" value="<?= e($token) ?>" placeholder="Pega el enlace o token del QR" required>
    </div>
    <div style="display:flex; gap:10px; flex-wrap:wrap;">
      <button type="submit" class="btn btn-primary">Registrar asistencia</button>
      <a href="/sgap/estudiante/dashboard.php" class="btn btn-secondary">Regresar a cursos</a>
    </div>
  </form>
</div>
<?php renderFooter(); ?>
