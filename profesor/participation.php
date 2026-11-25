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

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $studentId = (int)($_POST['student_id'] ?? 0);

    // Verificar que el estudiante esté inscrito
    $chk = $pdo->prepare('SELECT id FROM enrollments WHERE course_id = ? AND student_id = ? LIMIT 1');
    $chk->execute([$courseId, $studentId]);
    if (!$chk->fetch()) {
        $error = 'Estudiante no inscrito en este curso.';
    } elseif ($action === 'add') {
        $obs = trim($_POST['observation'] ?? '');
        if (mb_strlen($obs, 'UTF-8') > 100) {
            $error = 'La observación no puede exceder los 100 caracteres.';
        } else {
            $ins = $pdo->prepare('INSERT INTO participations (course_id, student_id, participation_date, observation) VALUES (?, ?, ?, ?)');
            $ins->execute([$courseId, $studentId, date('Y-m-d'), $obs !== '' ? $obs : null]);
            $success = 'Participación (+1) registrada correctamente.';
        }
    } elseif ($action === 'subtract') {
        // RF-11: nunca menor a 0
        $del = $pdo->prepare('DELETE FROM participations WHERE course_id = ? AND student_id = ? ORDER BY id DESC LIMIT 1');
        $del->execute([$courseId, $studentId]);
        if ($del->rowCount() > 0) {
            $success = 'Se restó -1 participación correctamente.';
        }
    }
}

$stuStmt = $pdo->prepare('
    SELECT u.id, u.full_name, u.email,
           (SELECT COUNT(*) FROM participations p WHERE p.course_id = ? AND p.student_id = u.id) AS total_part
    FROM enrollments e
    JOIN users u ON u.id = e.student_id
    WHERE e.course_id = ?
    ORDER BY u.full_name ASC
');
$stuStmt->execute([$courseId, $courseId]);
$students = $stuStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Participación - SGAP</title>
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
      <h1>Control de Participación: <?= e($course['name']) ?> (<?= e($course['code']) ?>)</h1>
      <a href="/sgap/profesor/courses.php" class="btn btn-secondary btn-sm">Volver a cursos</a>
    </div>

    <?php if ($error !== ''): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
    <?php if ($success !== ''): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>

    <div class="card">
      <?php if (empty($students)): ?>
        <p class="muted">No hay estudiantes inscritos en este curso.</p>
      <?php else: ?>
        <table>
          <thead>
            <tr>
              <th>Estudiante</th>
              <th>Total Participaciones</th>
              <th>Observación opcional (máx. 100)</th>
              <th>Acciones</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($students as $s): $total = (int)$s['total_part']; ?>
              <tr>
                <td><?= e($s['full_name']) ?></td>
                <td><strong><?= $total ?></strong></td>
                <td>
                  <form method="POST" id="add-form-<?= (int)$s['id'] ?>" class="actions-row">
                    <input type="hidden" name="action" value="add">
                    <input type="hidden" name="course_id" value="<?= $courseId ?>">
                    <input type="hidden" name="student_id" value="<?= (int)$s['id'] ?>">
                    <input type="text" name="observation" maxlength="100" placeholder="Opcional..." class="form-control" style="max-width:240px;">
                    <button type="submit" class="btn btn-success btn-sm">+1</button>
                  </form>
                </td>
                <td>
                  <form method="POST" style="display:inline;">
                    <input type="hidden" name="action" value="subtract">
                    <input type="hidden" name="course_id" value="<?= $courseId ?>">
                    <input type="hidden" name="student_id" value="<?= (int)$s['id'] ?>">
                    <button type="submit" class="btn btn-danger btn-sm" <?= $total <= 0 ? 'disabled' : '' ?>>-1</button>
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
