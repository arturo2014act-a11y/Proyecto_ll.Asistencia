<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/layout.php';

requireRole('profesor');
$user = currentUser();
$db = getDB();

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
    renderHeader('Participación', 'cursos');
    echo '<div class="card"><div class="alert alert-error">No tienes permiso para realizar esta acción</div><a href="/sgap/profesor/courses.php" class="btn btn-primary">Volver a Mis cursos</a></div>';
    renderFooter();
    exit;
}

$isOpen = $session['status'] === 'abierta';
$errorMsg = '';
$successMsg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$isOpen) {
        $errorMsg = 'La clase ya finalizó';
    } else {
        $studentId = (int)($_POST['student_id'] ?? 0);
        $delta = (int)($_POST['delta'] ?? 0);
        if (!in_array($delta, [1, -1], true)) {
            $errorMsg = 'No fue posible guardar los cambios. Intenta nuevamente';
        } else {
            $curStmt = $db->prepare('SELECT participation_count FROM attendance_records WHERE session_id = ? AND student_id = ?');
            $curStmt->execute([$sessionId, $studentId]);
            $row = $curStmt->fetch();
            if (!$row) {
                $errorMsg = 'No fue posible guardar los cambios. Intenta nuevamente';
            } else {
                $newVal = (int)$row['participation_count'] + $delta;
                if ($newVal < 0 || $newVal > 99) {
                    $errorMsg = 'No se permiten valores fuera de 0 a 99';
                } else {
                    $db->prepare('UPDATE attendance_records SET participation_count = ? WHERE session_id = ? AND student_id = ?')
                       ->execute([$newVal, $sessionId, $studentId]);
                    $successMsg = 'Participación actualizada. Total confirmado: ' . $newVal;
                }
            }
        }
    }
}

$recStmt = $db->prepare('
    SELECT r.student_id, r.participation_count, u.full_name, u.control_number
    FROM attendance_records r
    JOIN users u ON u.id = r.student_id
    WHERE r.session_id = ?
    ORDER BY u.full_name ASC
');
$recStmt->execute([$sessionId]);
$records = $recStmt->fetchAll();

renderHeader('Participación', 'cursos');
?>
<h1>Participación — <?= e($session['course_name']) ?> (<?= e($session['group_name']) ?>)</h1>
<p style="margin-bottom:16px;"><strong>Sesión:</strong> <?= date('d/m/Y H:i', strtotime($session['started_at'])) ?> (<?= $isOpen ? 'Abierta' : 'Cerrada - Solo consulta' ?>)</p>

<?php if ($successMsg !== ''): ?>
  <div class="alert alert-success"><?= e($successMsg) ?></div>
<?php endif; ?>
<?php if ($errorMsg !== ''): ?>
  <div class="alert alert-error"><?= e($errorMsg) ?></div>
<?php endif; ?>

<div style="margin-bottom:16px;">
  <a href="/sgap/profesor/attendance.php?session_id=<?= $sessionId ?>" class="btn btn-secondary">Volver a la clase activa</a>
</div>

<div class="card">
  <h2>Lista de participación (0 a 99)</h2>
  <?php if (empty($records)): ?>
    <div class="alert alert-info">Sin registros.</div>
  <?php else: ?>
    <div class="table-responsive">
      <table>
        <thead>
          <tr>
            <th>Número de control</th>
            <th>Nombre</th>
            <th>Total confirmado</th>
            <?php if ($isOpen): ?>
              <th>Acciones</th>
            <?php endif; ?>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($records as $r): ?>
            <tr>
              <td><?= e($r['control_number']) ?></td>
              <td><?= e($r['full_name']) ?></td>
              <td><strong><?= (int)$r['participation_count'] ?></strong></td>
              <?php if ($isOpen): ?>
                <td>
                  <div style="display:flex; gap:8px;">
                    <form method="POST" action="/sgap/profesor/participation.php?session_id=<?= $sessionId ?>" style="margin:0;">
                      <input type="hidden" name="session_id" value="<?= $sessionId ?>">
                      <input type="hidden" name="student_id" value="<?= (int)$r['student_id'] ?>">
                      <input type="hidden" name="delta" value="1">
                      <button type="submit" class="btn btn-success btn-sm">+1</button>
                    </form>
                    <form method="POST" action="/sgap/profesor/participation.php?session_id=<?= $sessionId ?>" style="margin:0;">
                      <input type="hidden" name="session_id" value="<?= $sessionId ?>">
                      <input type="hidden" name="student_id" value="<?= (int)$r['student_id'] ?>">
                      <input type="hidden" name="delta" value="-1">
                      <button type="submit" class="btn btn-danger btn-sm">-1</button>
                    </form>
                  </div>
                </td>
              <?php endif; ?>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>
<?php renderFooter(); ?>
