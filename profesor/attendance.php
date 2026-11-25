<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/layout.php';

requireRole('profesor');
$user = currentUser();
$db = getDB();

$errorMsg = '';
$successMsg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'start_class') {
    $courseId = (int)($_POST['course_id'] ?? 0);
    $cStmt = $db->prepare('SELECT id FROM courses WHERE id = ? AND professor_id = ?');
    $cStmt->execute([$courseId, $user['id']]);
    if (!$cStmt->fetch()) {
        $errorMsg = 'No tienes permiso para realizar esta acción';
    } else {
        $openStmt = $db->prepare('SELECT id FROM attendance_sessions WHERE course_id = ? AND status = "abierta" LIMIT 1');
        $openStmt->execute([$courseId]);
        if ($openStmt->fetch()) {
            $errorMsg = 'Ya existe una clase abierta';
        } else {
            $token = bin2hex(random_bytes(16));
            $now = date('Y-m-d H:i:s');
            $expires = date('Y-m-d H:i:s', time() + 600); // 10 minutos

            $db->beginTransaction();
            try {
                $ins = $db->prepare('INSERT INTO attendance_sessions (course_id, started_at, status, qr_token, qr_expires_at) VALUES (?, ?, "abierta", ?, ?)');
                $ins->execute([$courseId, $now, $token, $expires]);
                $newSessionId = (int)$db->lastInsertId();

                // Fijar lista de inscritos al iniciar en estado Pendiente
                $recIns = $db->prepare('
                    INSERT INTO attendance_records (session_id, student_id, status, participation_count)
                    SELECT ?, student_id, "Pendiente", 0 FROM enrollments WHERE course_id = ?
                ');
                $recIns->execute([$newSessionId, $courseId]);
                $db->commit();

                header('Location: /sgap/profesor/attendance.php?session_id=' . $newSessionId);
                exit;
            } catch (Throwable $e) {
                $db->rollBack();
                $errorMsg = 'No fue posible guardar los cambios. Intenta nuevamente';
            }
        }
    }
}

$sessionId = isset($_GET['session_id']) ? (int)$_GET['session_id'] : (int)($_POST['session_id'] ?? 0);

$sStmt = $db->prepare('
    SELECT s.*, c.name AS course_name, c.group_name, c.professor_id
    FROM attendance_sessions s
    JOIN courses c ON c.id = s.course_id
    WHERE s.id = ? AND c.professor_id = ?
');
$sStmt->execute([$sessionId, $user['id']]);
$session = $sStmt->fetch();

if (!$session) {
    renderHeader('Clase activa', 'cursos');
    echo '<div class="card"><div class="alert alert-error">' . e($errorMsg !== '' ? $errorMsg : 'No tienes permiso para realizar esta acción') . '</div><a href="/sgap/profesor/courses.php" class="btn btn-primary">Volver a Mis cursos</a></div>';
    renderFooter();
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postAction = $_POST['action'] ?? '';
    if ($postAction === 'save_manual') {
        $statuses = $_POST['status'] ?? [];
        $upd = $db->prepare('UPDATE attendance_records SET status = ? WHERE session_id = ? AND student_id = ?');
        foreach ($statuses as $stId => $stVal) {
            if (in_array($stVal, ['Presente', 'Ausente'], true)) {
                $upd->execute([$stVal, $sessionId, (int)$stId]);
            }
        }
        $successMsg = 'Asistencia registrada';
    } elseif ($postAction === 'close_class' && $session['status'] === 'abierta') {
        $db->beginTransaction();
        try {
            $db->prepare('UPDATE attendance_sessions SET status = "cerrada", closed_at = ?, qr_token = NULL WHERE id = ?')
               ->execute([date('Y-m-d H:i:s'), $sessionId]);
            $db->prepare('UPDATE attendance_records SET status = "Ausente" WHERE session_id = ? AND status = "Pendiente"')
               ->execute([$sessionId]);
            $db->commit();
            $sStmt->execute([$sessionId, $user['id']]);
            $session = $sStmt->fetch();
            $successMsg = 'La clase ha finalizado y los pendientes se marcaron como Ausente.';
        } catch (Throwable $e) {
            $db->rollBack();
            $errorMsg = 'No fue posible guardar los cambios. Intenta nuevamente';
        }
    }
}

$recStmt = $db->prepare('
    SELECT r.student_id, r.status, u.full_name, u.control_number
    FROM attendance_records r
    JOIN users u ON u.id = r.student_id
    WHERE r.session_id = ?
    ORDER BY u.full_name ASC
');
$recStmt->execute([$sessionId]);
$records = $recStmt->fetchAll();

$isOpen = $session['status'] === 'abierta';
$qrValid = $isOpen && $session['qr_token'] !== null && strtotime($session['qr_expires_at']) > time();
$qrUrl = 'http://' . ($_SERVER['HTTP_HOST'] ?? 'localhost:8000') . '/sgap/qr_checkin.php?token=' . urlencode((string)$session['qr_token']);

renderHeader($isOpen ? 'Clase activa' : 'Sesión cerrada', 'cursos');
?>
<h1><?= e($session['course_name']) ?> (<?= e($session['group_name']) ?>) — <?= $isOpen ? 'Clase activa' : 'Sesión cerrada' ?></h1>
<p style="margin-bottom:16px;"><strong>Inicio:</strong> <?= date('d/m/Y H:i', strtotime($session['started_at'])) ?></p>

<?php if ($successMsg !== ''): ?>
  <div class="alert alert-success"><?= e($successMsg) ?></div>
<?php endif; ?>
<?php if ($errorMsg !== ''): ?>
  <div class="alert alert-error"><?= e($errorMsg) ?></div>
<?php endif; ?>

<div class="card" style="display:flex; gap:12px; flex-wrap:wrap; align-items:center; justify-content:space-between;">
  <div style="display:flex; gap:10px; flex-wrap:wrap;">
    <a href="/sgap/profesor/participation.php?session_id=<?= $sessionId ?>" class="btn btn-primary">Registro de participación</a>
    <a href="/sgap/profesor/enrollments.php?course_id=<?= (int)$session['course_id'] ?>" class="btn btn-secondary">Volver al curso</a>
  </div>
  <?php if ($isOpen): ?>
    <form method="POST" action="/sgap/profesor/attendance.php?session_id=<?= $sessionId ?>" onsubmit="return confirm('¿Finalizar clase? Se desactivará el QR y los estudiantes pendientes pasarán a Ausente.');" style="margin:0;">
      <input type="hidden" name="action" value="close_class">
      <input type="hidden" name="session_id" value="<?= $sessionId ?>">
      <button type="submit" class="btn btn-danger">Finalizar clase</button>
    </form>
  <?php endif; ?>
</div>

<?php if ($isOpen): ?>
  <div class="card">
    <h2>Código QR de asistencia</h2>
    <?php if ($qrValid): ?>
      <p><strong>Vigencia hasta:</strong> <?= date('d/m/Y H:i', strtotime($session['qr_expires_at'])) ?> (10 minutos)</p>
      <div style="margin:12px 0;">
        <img src="https://api.qrserver.com/v1/create-qr-code/?size=180x180&data=<?= urlencode($qrUrl) ?>" alt="Código QR de asistencia" width="180" height="180" style="border:1px solid #D1D5DB; border-radius:8px; padding:6px; background:#fff;">
      </div>
      <p style="font-size:14px; word-break:break-all;"><strong>Enlace QR:</strong> <a href="<?= e($qrUrl) ?>"><?= e($qrUrl) ?></a></p>
    <?php else: ?>
      <div class="alert alert-error">QR vencido o inválido. Usa el registro manual.</div>
    <?php endif; ?>
  </div>
<?php endif; ?>

<div class="card">
  <h2>Lista de asistencia <?= $isOpen ? '(Registro manual)' : '(Corrección posterior)' ?></h2>
  <?php if (empty($records)): ?>
    <div class="alert alert-info">Sin registros.</div>
  <?php else: ?>
    <form method="POST" action="/sgap/profesor/attendance.php?session_id=<?= $sessionId ?>">
      <input type="hidden" name="action" value="save_manual">
      <input type="hidden" name="session_id" value="<?= $sessionId ?>">
      <div class="table-responsive">
        <table>
          <thead>
            <tr>
              <th>Número de control</th>
              <th>Nombre</th>
              <th>Estado actual</th>
              <th>Marcar</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($records as $r): ?>
              <tr>
                <td><?= e($r['control_number']) ?></td>
                <td><?= e($r['full_name']) ?></td>
                <td>
                  <?php if ($r['status'] === 'Presente'): ?>
                    <span class="badge badge-presente">✓ Presente</span>
                  <?php elseif ($r['status'] === 'Ausente'): ?>
                    <span class="badge badge-ausente">✕ Ausente</span>
                  <?php else: ?>
                    <span class="badge badge-pendiente">• Pendiente</span>
                  <?php endif; ?>
                </td>
                <td>
                  <select name="status[<?= (int)$r['student_id'] ?>]" style="min-width:140px;">
                    <option value="">-- Sin cambio --</option>
                    <option value="Presente" <?= $r['status'] === 'Presente' ? 'selected' : '' ?>>Presente</option>
                    <option value="Ausente" <?= $r['status'] === 'Ausente' ? 'selected' : '' ?>>Ausente</option>
                  </select>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <div style="margin-top:16px;">
        <button type="submit" class="btn btn-primary">Guardar</button>
      </div>
    </form>
  <?php endif; ?>
</div>
<?php renderFooter(); ?>
