<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/layout.php';

requireRole('profesor');
$user = currentUser();
$db = getDB();

$courseId = isset($_GET['course_id']) ? (int)$_GET['course_id'] : (int)($_POST['course_id'] ?? 0);
$stmt = $db->prepare('SELECT * FROM courses WHERE id = ? AND professor_id = ?');
$stmt->execute([$courseId, $user['id']]);
$course = $stmt->fetch();

if (!$course) {
    renderHeader('Detalle de curso', 'cursos');
    echo '<div class="card"><div class="alert alert-error">No tienes permiso para realizar esta acción</div><a href="/sgap/profesor/courses.php" class="btn btn-primary">Volver a Mis cursos</a></div>';
    renderFooter();
    exit;
}

$errorMsg = '';
$successMsg = '';

// Verificar si hay sesión abierta
$openStmt = $db->prepare('SELECT id FROM attendance_sessions WHERE course_id = ? AND status = "abierta" LIMIT 1');
$openStmt->execute([$courseId]);
$openSession = $openStmt->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'enroll') {
    $studentId = (int)($_POST['student_id'] ?? 0);
    if ($openSession) {
        $errorMsg = 'Ya existe una clase abierta';
    } elseif ($studentId <= 0) {
        $errorMsg = 'Este campo es obligatorio';
    } else {
        $cntStmt = $db->prepare('SELECT COUNT(*) FROM enrollments WHERE course_id = ?');
        $cntStmt->execute([$courseId]);
        $currentCount = (int)$cntStmt->fetchColumn();

        if ($currentCount >= 50) {
            $errorMsg = 'Máximo 50 estudiantes por curso';
        } else {
            $dupStmt = $db->prepare('SELECT id FROM enrollments WHERE course_id = ? AND student_id = ?');
            $dupStmt->execute([$courseId, $studentId]);
            if ($dupStmt->fetch()) {
                $errorMsg = 'El estudiante ya está inscrito';
            } else {
                $ins = $db->prepare('INSERT INTO enrollments (course_id, student_id) VALUES (?, ?)');
                $ins->execute([$courseId, $studentId]);
                $successMsg = 'Estudiante agregado correctamente.';
            }
        }
    }
}

$enrolledStmt = $db->prepare('
    SELECT u.id, u.full_name, u.control_number
    FROM enrollments e
    JOIN users u ON u.id = e.student_id
    WHERE e.course_id = ?
    ORDER BY u.full_name ASC
');
$enrolledStmt->execute([$courseId]);
$enrolled = $enrolledStmt->fetchAll();

$availStmt = $db->prepare('
    SELECT id, full_name, control_number
    FROM users
    WHERE role = "estudiante"
      AND id NOT IN (SELECT student_id FROM enrollments WHERE course_id = ?)
    ORDER BY full_name ASC
');
$availStmt->execute([$courseId]);
$availableStudents = $availStmt->fetchAll();

$sessionsStmt = $db->prepare('SELECT id, started_at, closed_at, status FROM attendance_sessions WHERE course_id = ? ORDER BY started_at DESC');
$sessionsStmt->execute([$courseId]);
$sessions = $sessionsStmt->fetchAll();

renderHeader('Detalle de curso', 'cursos');
?>
<h1><?= e($course['name']) ?> (<?= e($course['group_name']) ?>)</h1>
<?php if (!empty($course['description'])): ?>
  <p style="margin-bottom:16px;"><?= e($course['description']) ?></p>
<?php endif; ?>

<?php if ($successMsg !== ''): ?>
  <div class="alert alert-success"><?= e($successMsg) ?></div>
<?php endif; ?>
<?php if ($errorMsg !== ''): ?>
  <div class="alert alert-error"><?= e($errorMsg) ?></div>
<?php endif; ?>

<div class="card" style="display:flex; gap:12px; flex-wrap:wrap; align-items:center; justify-content:space-between;">
  <div>
    <strong>Estudiantes inscritos:</strong> <?= count($enrolled) ?> / 50
  </div>
  <div style="display:flex; gap:10px; flex-wrap:wrap;">
    <?php if ($openSession): ?>
      <a href="/sgap/profesor/attendance.php?session_id=<?= (int)$openSession['id'] ?>" class="btn btn-success">Ir a clase activa</a>
    <?php else: ?>
      <form method="POST" action="/sgap/profesor/attendance.php" style="margin:0;">
        <input type="hidden" name="action" value="start_class">
        <input type="hidden" name="course_id" value="<?= $courseId ?>">
        <button type="submit" class="btn btn-primary">Iniciar clase</button>
      </form>
    <?php endif; ?>
    <a href="/sgap/profesor/courses.php" class="btn btn-secondary">Volver a Mis cursos</a>
  </div>
</div>

<div class="card">
  <h2>Agregar estudiante</h2>
  <?php if ($openSession): ?>
    <div class="alert alert-info">No se pueden agregar estudiantes mientras haya una sesión abierta.</div>
  <?php elseif (empty($availableStudents)): ?>
    <div class="alert alert-info">No hay más estudiantes disponibles para inscribir.</div>
  <?php else: ?>
    <form method="POST" action="/sgap/profesor/enrollments.php?course_id=<?= $courseId ?>" style="display:flex; gap:10px; flex-wrap:wrap; align-items:flex-end;">
      <input type="hidden" name="action" value="enroll">
      <input type="hidden" name="course_id" value="<?= $courseId ?>">
      <div class="form-group" style="flex:1; min-width:220px; margin-bottom:0;">
        <label for="student_id">Estudiante <span class="required-mark">*</span></label>
        <select id="student_id" name="student_id" required>
          <option value="">Selecciona un estudiante...</option>
          <?php foreach ($availableStudents as $st): ?>
            <option value="<?= (int)$st['id'] ?>"><?= e($st['control_number'] . ' - ' . $st['full_name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <button type="submit" class="btn btn-primary">Agregar estudiante</button>
    </form>
  <?php endif; ?>
</div>

<div class="card">
  <h2>Lista de estudiantes</h2>
  <?php if (empty($enrolled)): ?>
    <div class="alert alert-info">Sin registros.</div>
  <?php else: ?>
    <div class="table-responsive">
      <table>
        <thead>
          <tr>
            <th>Número de control</th>
            <th>Nombre completo</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($enrolled as $st): ?>
            <tr>
              <td><?= e($st['control_number']) ?></td>
              <td><?= e($st['full_name']) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<div class="card">
  <h2>Sesiones anteriores</h2>
  <?php if (empty($sessions)): ?>
    <div class="alert alert-info">Sin registros.</div>
  <?php else: ?>
    <div class="table-responsive">
      <table>
        <thead>
          <tr>
            <th>Fecha y hora</th>
            <th>Estado</th>
            <th>Acciones</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($sessions as $s): ?>
            <tr>
              <td><?= date('d/m/Y H:i', strtotime($s['started_at'])) ?></td>
              <td><?= $s['status'] === 'abierta' ? 'Abierta' : 'Cerrada' ?></td>
              <td>
                <a href="/sgap/profesor/attendance.php?session_id=<?= (int)$s['id'] ?>" class="btn btn-secondary btn-sm">
                  <?= $s['status'] === 'abierta' ? 'Ver clase activa' : 'Consultar / Corregir' ?>
                </a>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>
<?php renderFooter(); ?>
