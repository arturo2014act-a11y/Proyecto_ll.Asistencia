<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/layout.php';

requireRole('profesor');
$user = currentUser();
$db = getDB();

$coursesStmt = $db->prepare('SELECT id, name, group_name FROM courses WHERE professor_id = ? ORDER BY name ASC');
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

$studentCount = 0;
$closedSessions = [];
$rows = [];

if ($selectedCourse) {
    $cntStmt = $db->prepare('SELECT COUNT(*) FROM enrollments WHERE course_id = ?');
    $cntStmt->execute([$selectedCourseId]);
    $studentCount = (int)$cntStmt->fetchColumn();

    $sessStmt = $db->prepare('SELECT id, started_at FROM attendance_sessions WHERE course_id = ? AND status = "cerrada" ORDER BY started_at DESC');
    $sessStmt->execute([$selectedCourseId]);
    $closedSessions = $sessStmt->fetchAll();

    // Estadísticas por estudiante solo en sesiones cerradas donde aparece en la lista de inscritos
    $repStmt = $db->prepare('
        SELECT u.id, u.control_number, u.full_name,
               COUNT(r.id) AS applicable_sessions,
               SUM(CASE WHEN r.status = "Presente" THEN 1 ELSE 0 END) AS present_count,
               COALESCE(SUM(r.participation_count), 0) AS total_participation
        FROM enrollments e
        JOIN users u ON u.id = e.student_id
        LEFT JOIN attendance_sessions s ON s.course_id = e.course_id AND s.status = "cerrada"
        LEFT JOIN attendance_records r ON r.session_id = s.id AND r.student_id = u.id
        WHERE e.course_id = ?
        GROUP BY u.id, u.control_number, u.full_name
        ORDER BY u.full_name ASC
    ');
    $repStmt->execute([$selectedCourseId]);
    $rows = $repStmt->fetchAll();
}

renderHeader('Reportes', 'reportes');
?>
<h1>Reportes del profesor</h1>

<?php if (empty($courses)): ?>
  <div class="card">
    <div class="alert alert-info">Sin registros. No tienes cursos creados.</div>
  </div>
<?php else: ?>
  <div class="card">
    <form method="GET" action="/sgap/profesor/reports.php" style="display:flex; gap:12px; flex-wrap:wrap; align-items:flex-end;">
      <div class="form-group" style="flex:1; min-width:240px; margin-bottom:0;">
        <label for="course_id">Seleccionar curso</label>
        <select id="course_id" name="course_id" onchange="this.form.submit()">
          <?php foreach ($courses as $c): ?>
            <option value="<?= (int)$c['id'] ?>" <?= (int)$c['id'] === $selectedCourseId ? 'selected' : '' ?>>
              <?= e($c['name'] . ' (' . $c['group_name'] . ')') ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <noscript><button type="submit" class="btn btn-primary">Consultar</button></noscript>
    </form>
  </div>

  <?php if ($selectedCourse): ?>
    <div class="grid-stats">
      <div class="stat-card">
        <div class="stat-label">Estudiantes actuales</div>
        <div class="stat-value"><?= $studentCount ?></div>
      </div>
      <div class="stat-card">
        <div class="stat-label">Sesiones cerradas</div>
        <div class="stat-value"><?= count($closedSessions) ?></div>
      </div>
    </div>

    <div class="card">
      <h2>Estadísticas por estudiante</h2>
      <?php if (empty($rows)): ?>
        <div class="alert alert-info">Sin registros.</div>
      <?php else: ?>
        <div class="table-responsive">
          <table>
            <thead>
              <tr>
                <th>Número de control</th>
                <th>Nombre</th>
                <th>Asistencias</th>
                <th>Ausencias</th>
                <th>Porcentaje</th>
                <th>Participaciones</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($rows as $r): ?>
                <?php
                  $applicable = (int)$r['applicable_sessions'];
                  $present = (int)$r['present_count'];
                  $absent = $applicable - $present;
                  $pctText = $applicable > 0 ? number_format(round(($present / $applicable) * 100, 1), 1, ',', '') . ' %' : 'Sin registros';
                ?>
                <tr>
                  <td><?= e($r['control_number']) ?></td>
                  <td><?= e($r['full_name']) ?></td>
                  <td><?= $present ?></td>
                  <td><?= $absent ?></td>
                  <td><?= e($pctText) ?></td>
                  <td><?= (int)$r['total_participation'] ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>

    <div class="card">
      <h2>Sesiones cerradas (Consultar / Corregir asistencia)</h2>
      <?php if (empty($closedSessions)): ?>
        <div class="alert alert-info">Sin registros.</div>
      <?php else: ?>
        <div class="table-responsive">
          <table>
            <thead>
              <tr>
                <th>Fecha y hora</th>
                <th>Acciones</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($closedSessions as $cs): ?>
                <tr>
                  <td><?= date('d/m/Y H:i', strtotime($cs['started_at'])) ?></td>
                  <td>
                    <a href="/sgap/profesor/attendance.php?session_id=<?= (int)$cs['id'] ?>" class="btn btn-secondary btn-sm">Consultar / Corregir</a>
                  </td>
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
