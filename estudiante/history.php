<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/layout.php';

requireRole('estudiante');
$user = currentUser();
$db = getDB();

$coursesStmt = $db->prepare('
    SELECT c.id, c.name, c.group_name
    FROM enrollments e
    JOIN courses c ON c.id = e.course_id
    WHERE e.student_id = ?
    ORDER BY c.name ASC
');
$coursesStmt->execute([$user['id']]);
$courses = $coursesStmt->fetchAll();

$selectedCourseId = isset($_GET['course_id']) ? (int)$_GET['course_id'] : (int)($courses[0]['id'] ?? 0);
$selectedCourse = null;
foreach ($courses as $c) {
    if ((int)$c['id'] === $selectedCourseId) {
        $selectedCourse = $c;
        break;
    }
}

$history = [];
$presentCount = 0;
$absentCount = 0;
$totalParticipation = 0;
$pctText = 'Sin registros';

if ($selectedCourse) {
    $histStmt = $db->prepare('
        SELECT s.started_at, r.status, r.participation_count
        FROM attendance_records r
        JOIN attendance_sessions s ON s.id = r.session_id
        WHERE s.course_id = ? AND s.status = "cerrada" AND r.student_id = ?
        ORDER BY s.started_at DESC
    ');
    $histStmt->execute([$selectedCourseId, $user['id']]);
    $history = $histStmt->fetchAll();

    $applicable = count($history);
    foreach ($history as $h) {
        if ($h['status'] === 'Presente') {
            $presentCount++;
        }
        $totalParticipation += (int)$h['participation_count'];
    }
    $absentCount = $applicable - $presentCount;
    if ($applicable > 0) {
        $pctText = number_format(round(($presentCount / $applicable) * 100, 1), 1, ',', '') . ' %';
    }
}

renderHeader('Historial', 'historial');
?>
<h1>Historial de asistencias y participación</h1>

<?php if (empty($courses)): ?>
  <div class="card">
    <div class="alert alert-info">Sin registros.</div>
  </div>
<?php else: ?>
  <div class="card">
    <form method="GET" action="/sgap/estudiante/history.php">
      <div class="form-group" style="margin-bottom:0;">
        <label for="course_id">Seleccionar curso</label>
        <select id="course_id" name="course_id" onchange="this.form.submit()">
          <?php foreach ($courses as $c): ?>
            <option value="<?= (int)$c['id'] ?>" <?= (int)$c['id'] === $selectedCourseId ? 'selected' : '' ?>>
              <?= e($c['name'] . ' (' . $c['group_name'] . ')') ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
    </form>
  </div>

  <?php if ($selectedCourse): ?>
    <div class="grid-stats">
      <div class="stat-card">
        <div class="stat-label">Asistencias</div>
        <div class="stat-value"><?= $presentCount ?></div>
      </div>
      <div class="stat-card">
        <div class="stat-label">Ausencias</div>
        <div class="stat-value"><?= $absentCount ?></div>
      </div>
      <div class="stat-card">
        <div class="stat-label">Porcentaje</div>
        <div class="stat-value"><?= e($pctText) ?></div>
      </div>
      <div class="stat-card">
        <div class="stat-label">Participaciones</div>
        <div class="stat-value"><?= $totalParticipation ?></div>
      </div>
    </div>

    <div class="card">
      <h2>Historial por sesión</h2>
      <?php if (empty($history)): ?>
        <div class="alert alert-info">Sin registros.</div>
      <?php else: ?>
        <div class="table-responsive">
          <table>
            <thead>
              <tr>
                <th>Fecha</th>
                <th>Hora</th>
                <th>Estado</th>
                <th>Participaciones</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($history as $h): ?>
                <tr>
                  <td><?= date('d/m/Y', strtotime($h['started_at'])) ?></td>
                  <td><?= date('H:i', strtotime($h['started_at'])) ?></td>
                  <td>
                    <?php if ($h['status'] === 'Presente'): ?>
                      <span class="badge badge-presente">✓ Presente</span>
                    <?php else: ?>
                      <span class="badge badge-ausente">✕ Ausente</span>
                    <?php endif; ?>
                  </td>
                  <td><?= (int)$h['participation_count'] ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  <?php endif; ?>
<?php endif; ?>
<?php renderFooter(); ?>
