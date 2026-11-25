<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/layout.php';

requireRole('estudiante');
$user = currentUser();
$db = getDB();

$stmt = $db->prepare('
    SELECT c.id, c.name, c.group_name, c.description, u.full_name AS professor_name
    FROM enrollments e
    JOIN courses c ON c.id = e.course_id
    JOIN users u ON u.id = c.professor_id
    WHERE e.student_id = ?
    ORDER BY c.name ASC
');
$stmt->execute([$user['id']]);
$courses = $stmt->fetchAll();

renderHeader('Inicio - Estudiante', 'inicio');
?>
<h1>Inicio del estudiante</h1>

<div class="card" style="display:flex; gap:12px; flex-wrap:wrap; align-items:center; justify-content:space-between;">
  <div>
    <strong>Número de control:</strong> <?= e((string)$user['control_number']) ?>
  </div>
  <div style="display:flex; gap:10px; flex-wrap:wrap;">
    <a href="/sgap/qr_checkin.php" class="btn btn-primary">Registrar asistencia</a>
    <a href="/sgap/estudiante/history.php" class="btn btn-secondary">Historial</a>
  </div>
</div>

<div class="card" id="cursos">
  <h2>Mis cursos inscritos</h2>
  <?php if (empty($courses)): ?>
    <div class="alert alert-info">Sin registros. Aún no estás inscrito en ningún curso.</div>
  <?php else: ?>
    <div class="table-responsive">
      <table>
        <thead>
          <tr>
            <th>Curso</th>
            <th>Grupo</th>
            <th>Profesor</th>
            <th>Acciones</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($courses as $c): ?>
            <tr>
              <td><?= e($c['name']) ?></td>
              <td><?= e($c['group_name']) ?></td>
              <td><?= e($c['professor_name']) ?></td>
              <td>
                <a href="/sgap/estudiante/history.php?course_id=<?= (int)$c['id'] ?>" class="btn btn-primary btn-sm">Ver historial</a>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>
<?php renderFooter(); ?>
