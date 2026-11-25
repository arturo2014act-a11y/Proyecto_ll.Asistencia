<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';

requireRole('profesor');
$user = currentUser();
$pdo = getDB();

$courseId = (int)($_GET['course_id'] ?? $_POST['course_id'] ?? 0);
$stmt = $pdo->prepare('SELECT * FROM courses WHERE id = ? AND professor_id = ? LIMIT 1');
$stmt->execute([$courseId, $user['id']]);
$course = $stmt->fetch();

if (!$course) {
    header('Location: /sgap/profesor/courses.php');
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'enroll') {
    $email = trim($_POST['email'] ?? '');
    $cntStmt = $pdo->prepare('SELECT COUNT(*) FROM enrollments WHERE course_id = ?');
    $cntStmt->execute([$courseId]);
    $currentCount = (int)$cntStmt->fetchColumn();

    if ($currentCount >= 60) {
        $error = 'El curso ha alcanzado el límite máximo de 60 estudiantes inscritos.';
    } else {
        $stuStmt = $pdo->prepare('SELECT id, full_name, role FROM users WHERE email = ? LIMIT 1');
        $stuStmt->execute([$email]);
        $student = $stuStmt->fetch();

        if (!$student || $student['role'] !== 'estudiante') {
            $error = 'No se encontró un estudiante con ese correo';
        } else {
            $dupStmt = $pdo->prepare('SELECT id FROM enrollments WHERE course_id = ? AND student_id = ? LIMIT 1');
            $dupStmt->execute([$courseId, $student['id']]);
            if ($dupStmt->fetch()) {
                $error = 'El estudiante ya está inscrito en este curso';
            } else {
                $ins = $pdo->prepare('INSERT INTO enrollments (course_id, student_id) VALUES (?, ?)');
                $ins->execute([$courseId, $student['id']]);
                $success = 'Estudiante inscrito correctamente: ' . $student['full_name'];
            }
        }
    }
}

$listStmt = $pdo->prepare('
    SELECT u.id, u.full_name, u.email, e.enrolled_at
    FROM enrollments e
    JOIN users u ON u.id = e.student_id
    WHERE e.course_id = ?
    ORDER BY u.full_name ASC
');
$listStmt->execute([$courseId]);
$students = $listStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Inscripciones - SGAP</title>
  <link rel="stylesheet" href="/sgap/assets/css/style.css">
</head>
<body>
  <nav class="navbar">
    <div class="brand">SGAP · Profesor</div>
    <div class="nav-links">
      <a href="/sgap/profesor/dashboard.php">Dashboard</a>
      <a href="/sgap/profesor/courses.php">Cursos</a>
      <a href="/sgap/logout.php" class="btn btn-secondary btn-sm">Cerrar sesión</a>
    </div>
  </nav>

  <div class="container">
    <div class="card-header">
      <h1>Inscripciones: <?= e($course['name']) ?> (<?= e($course['code']) ?>)</h1>
      <a href="/sgap/profesor/courses.php" class="btn btn-secondary btn-sm">Volver a cursos</a>
    </div>

    <?php if ($error !== ''): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
    <?php if ($success !== ''): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>

    <div class="card">
      <h2>Inscribir Estudiante (<?= count($students) ?>/60)</h2>
      <form method="POST" class="actions-row" novalidate>
        <input type="hidden" name="action" value="enroll">
        <input type="hidden" name="course_id" value="<?= $courseId ?>">
        <input type="email" name="email" placeholder="Correo del estudiante (usuario@dominio.com)" class="form-control" style="max-width:360px;" required>
        <button type="submit" class="btn btn-primary">Inscribir</button>
      </form>
    </div>

    <div class="card">
      <h2>Estudiantes Inscritos</h2>
      <?php if (empty($students)): ?>
        <p class="muted">No hay estudiantes inscritos en este curso aún.</p>
      <?php else: ?>
        <table>
          <thead>
            <tr><th>Nombre completo</th><th>Correo</th><th>Fecha de inscripción</th></tr>
          </thead>
          <tbody>
            <?php foreach ($students as $s): ?>
              <tr>
                <td><?= e($s['full_name']) ?></td>
                <td><?= e($s['email']) ?></td>
                <td><?= e($s['enrolled_at']) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>
  </div>
</body>
</html>
