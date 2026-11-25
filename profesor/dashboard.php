<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/layout.php';

requireRole('profesor');
$user = currentUser();
$db = getDB();

$stmt = $db->prepare('
    SELECT c.id, c.name, c.group_name, c.description,
           (SELECT COUNT(*) FROM enrollments e WHERE e.course_id = c.id) AS total_students,
           (SELECT COUNT(*) FROM attendance_sessions s WHERE s.course_id = c.id AND s.status = "cerrada") AS closed_sessions,
           (SELECT id FROM attendance_sessions s WHERE s.course_id = c.id AND s.status = "abierta" LIMIT 1) AS open_session_id
    FROM courses c
    WHERE c.professor_id = ?
    ORDER BY c.created_at DESC
');
$stmt->execute([$user['id']]);
$courses = $stmt->fetchAll();

renderHeader('Inicio - Profesor', 'inicio');
?>
<h1>Inicio del profesor</h1>

<div class="grid-stats">
  <div class="stat-card">
    <div class="stat-label">Mis cursos</div>
    <div class="stat-value"><?= count($courses) ?></div>
  </div>
  <div class="stat-card">
    <div class="stat-label">Acceso rápido</div>
    <div style="margin-top:8px;">
      <a href="/sgap/profesor/courses.php?action=new" class="btn btn-primary btn-sm">Crear curso</a>
      <a href="/sgap/profesor/reports.php" class="btn btn-secondary btn-sm">Reportes</a>
    </div>
  </div>
</div>

<div class="card">
  <h2>Resumen de cursos propios</h2>
  <?php if (empty($courses)): ?>
    <div class="alert alert-info">Sin registros. Aún no tienes cursos creados.</div>
    <a href="/sgap/profesor/courses.php?action=new" class="btn btn-primary">Crear curso</a>
  <?php else: ?>
    <div class="table-responsive">
      <table>
        <thead>
          <tr>
            <th>Curso</th>
            <th>Grupo</th>
            <th>Estudiantes</th>
            <th>Sesiones cerradas</th>
            <th>Acciones</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($courses as $c): ?>
            <tr>
              <td><?= e($c['name']) ?></td>
              <td><?= e($c['group_name']) ?></td>
              <td><?= (int)$c['total_students'] ?></td>
              <td><?= (int)$c['closed_sessions'] ?></td>
              <td>
                <a href="/sgap/profesor/enrollments.php?course_id=<?= (int)$c['id'] ?>" class="btn btn-primary btn-sm">Ver</a>
                <?php if ($c['open_session_id']): ?>
                  <a href="/sgap/profesor/attendance.php?session_id=<?= (int)$c['open_session_id'] ?>" class="btn btn-success btn-sm">Clase activa</a>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>
<?php renderFooter(); ?>
