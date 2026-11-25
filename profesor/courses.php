<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/validators.php';

requireRole('profesor');
$user = currentUser();
$pdo = getDB();

$errors = [];
$flashSuccess = '';
$editCourse = null;

// Eliminar curso (RF-05)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    $courseId = (int)($_POST['course_id'] ?? 0);
    $del = $pdo->prepare('DELETE FROM courses WHERE id = ? AND professor_id = ?');
    $del->execute([$courseId, $user['id']]);
    if ($del->rowCount() > 0) {
        $flashSuccess = 'Curso y todos sus registros asociados eliminados correctamente.';
    }
}

// Crear o editar curso (RF-04, RF-05)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($_POST['action'] ?? '', ['create', 'update'], true)) {
    $action = $_POST['action'];
    $courseId = (int)($_POST['course_id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $code = trim($_POST['code'] ?? '');
    $schedule = trim($_POST['schedule'] ?? '');

    $errors = validateCourseData($name, $code, $schedule);

    if (empty($errors)) {
        // Verificar clave única por profesor
        if ($action === 'create') {
            $chk = $pdo->prepare('SELECT id FROM courses WHERE professor_id = ? AND code = ? LIMIT 1');
            $chk->execute([$user['id'], $code]);
        } else {
            $chk = $pdo->prepare('SELECT id FROM courses WHERE professor_id = ? AND code = ? AND id != ? LIMIT 1');
            $chk->execute([$user['id'], $code, $courseId]);
        }
        if ($chk->fetch()) {
            $errors['code'] = 'Ya tienes un curso registrado con esta clave/grupo.';
        } else {
            if ($action === 'create') {
                $ins = $pdo->prepare('INSERT INTO courses (professor_id, name, code, schedule) VALUES (?, ?, ?, ?)');
                $ins->execute([$user['id'], $name, $code, $schedule]);
                $flashSuccess = 'Curso creado correctamente.';
            } else {
                $upd = $pdo->prepare('UPDATE courses SET name = ?, code = ?, schedule = ? WHERE id = ? AND professor_id = ?');
                $upd->execute([$name, $code, $schedule, $courseId, $user['id']]);
                $flashSuccess = 'Curso actualizado correctamente.';
            }
        }
    }
    if (!empty($errors) && $action === 'update') {
        $editCourse = ['id' => $courseId, 'name' => $name, 'code' => $code, 'schedule' => $schedule];
    }
}

if (isset($_GET['edit']) && $editCourse === null) {
    $stmt = $pdo->prepare('SELECT id, name, code, schedule FROM courses WHERE id = ? AND professor_id = ? LIMIT 1');
    $stmt->execute([(int)$_GET['edit'], $user['id']]);
    $editCourse = $stmt->fetch() ?: null;
}

$coursesStmt = $pdo->prepare('SELECT c.*, (SELECT COUNT(*) FROM enrollments e WHERE e.course_id = c.id) AS total_students FROM courses c WHERE c.professor_id = ? ORDER BY c.created_at DESC');
$coursesStmt->execute([$user['id']]);
$courses = $coursesStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Mis Cursos - SGAP</title>
  <link rel="stylesheet" href="/sgap/assets/css/style.css">
</head>
<body>
  <nav class="navbar">
    <div class="brand">SGAP · Profesor</div>
    <div class="nav-links">
      <a href="/sgap/profesor/dashboard.php">Dashboard</a>
      <a href="/sgap/profesor/courses.php"><strong>Cursos</strong></a>
      <span class="muted"><?= e($user['full_name']) ?></span>
      <a href="/sgap/logout.php" class="btn btn-secondary btn-sm">Cerrar sesión</a>
    </div>
  </nav>

  <div class="container">
    <h1>Gestión de Cursos</h1>
    <?php if ($flashSuccess !== ''): ?>
      <div class="alert alert-success"><?= e($flashSuccess) ?></div>
    <?php endif; ?>

    <div class="card">
      <h2><?= $editCourse ? 'Editar Curso' : 'Nuevo Curso' ?></h2>
      <form method="POST" novalidate>
        <input type="hidden" name="action" value="<?= $editCourse ? 'update' : 'create' ?>">
        <?php if ($editCourse): ?>
          <input type="hidden" name="course_id" value="<?= (int)$editCourse['id'] ?>">
        <?php endif; ?>
        <div class="grid-3">
          <div class="form-group">
            <label for="name">Nombre del curso</label>
            <input type="text" id="name" name="name" class="form-control <?= isset($errors['name']) ? 'is-invalid' : '' ?>" value="<?= e($editCourse['name'] ?? ($_POST['name'] ?? '')) ?>" required>
            <?php if (isset($errors['name'])): ?><div class="field-error"><?= e($errors['name']) ?></div><?php endif; ?>
          </div>
          <div class="form-group">
            <label for="code">Clave / Grupo</label>
            <input type="text" id="code" name="code" class="form-control <?= isset($errors['code']) ? 'is-invalid' : '' ?>" value="<?= e($editCourse['code'] ?? ($_POST['code'] ?? '')) ?>" required>
            <?php if (isset($errors['code'])): ?><div class="field-error"><?= e($errors['code']) ?></div><?php endif; ?>
          </div>
          <div class="form-group">
            <label for="schedule">Horario</label>
            <input type="text" id="schedule" name="schedule" placeholder="Lun-Mié 10:00-12:00" class="form-control <?= isset($errors['schedule']) ? 'is-invalid' : '' ?>" value="<?= e($editCourse['schedule'] ?? ($_POST['schedule'] ?? '')) ?>" required>
            <?php if (isset($errors['schedule'])): ?><div class="field-error"><?= e($errors['schedule']) ?></div><?php endif; ?>
          </div>
        </div>
        <div class="actions-row">
          <button type="submit" class="btn btn-primary"><?= $editCourse ? 'Guardar cambios' : 'Crear curso' ?></button>
          <?php if ($editCourse): ?>
            <a href="/sgap/profesor/courses.php" class="btn btn-secondary">Cancelar</a>
          <?php endif; ?>
        </div>
      </form>
    </div>

    <div class="card">
      <h2>Mis Cursos</h2>
      <?php if (empty($courses)): ?>
        <div class="alert alert-info">Aún no tienes cursos registrados/inscritos</div>
      <?php else: ?>
        <table>
          <thead>
            <tr>
              <th>Clave</th>
              <th>Nombre</th>
              <th>Horario</th>
              <th>Estudiantes</th>
              <th>Acciones</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($courses as $c): ?>
              <tr>
                <td><strong><?= e($c['code']) ?></strong></td>
                <td><?= e($c['name']) ?></td>
                <td><?= e($c['schedule']) ?></td>
                <td><?= (int)$c['total_students'] ?></td>
                <td class="actions-row">
                  <a href="/sgap/profesor/enrollments.php?course_id=<?= (int)$c['id'] ?>" class="btn btn-secondary btn-sm">Inscribir</a>
                  <a href="/sgap/profesor/attendance.php?course_id=<?= (int)$c['id'] ?>" class="btn btn-primary btn-sm">Asistencia</a>
                  <a href="/sgap/profesor/participation.php?course_id=<?= (int)$c['id'] ?>" class="btn btn-success btn-sm">Participación</a>
                  <a href="/sgap/profesor/courses.php?edit=<?= (int)$c['id'] ?>" class="btn btn-warning btn-sm">Editar</a>
                  <form method="POST" style="display:inline;" onsubmit="return confirm('¿Estás seguro de eliminar este curso y todos sus registros asociados?');">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="course_id" value="<?= (int)$c['id'] ?>">
                    <button type="submit" class="btn btn-danger btn-sm">Eliminar</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>
  </div>
</body>
</html>
