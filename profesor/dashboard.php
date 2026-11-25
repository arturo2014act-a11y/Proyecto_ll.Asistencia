<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';

requireRole('profesor');
$user = currentUser();
$pdo = getDB();

$coursesStmt = $pdo->prepare('SELECT * FROM courses WHERE professor_id = ? ORDER BY created_at DESC');
$coursesStmt->execute([$user['id']]);
$courses = $coursesStmt->fetchAll();

$selectedCourseId = (int)($_GET['course_id'] ?? ($courses[0]['id'] ?? 0));
$selectedCourse = null;
foreach ($courses as $c) {
    if ((int)$c['id'] === $selectedCourseId) {
        $selectedCourse = $c;
        break;
    }
}

$metrics = [
    'sessions' => 0,
    'attendance_pct' => 0.0,
    'participations' => 0,
];
$studentStats = [];

if ($selectedCourse) {
    $cid = (int)$selectedCourse['id'];

    $sessStmt = $pdo->prepare('SELECT COUNT(*) FROM attendance_sessions WHERE course_id = ?');
    $sessStmt->execute([$cid]);
    $totalSessions = (int)$sessStmt->fetchColumn();

    $partStmt = $pdo->prepare('SELECT COUNT(*) FROM participations WHERE course_id = ?');
    $partStmt->execute([$cid]);
    $totalPart = (int)$partStmt->fetchColumn();

    $stuStmt = $pdo->prepare('
        SELECT u.id, u.full_name, u.email,
               SUM(CASE WHEN ar.status = "Presente" THEN 1 ELSE 0 END) AS presentes,
               SUM(CASE WHEN ar.status = "Falta" THEN 1 ELSE 0 END) AS faltas,
               SUM(CASE WHEN ar.status = "Retardo" THEN 1 ELSE 0 END) AS retardos,
               (SELECT COUNT(*) FROM participations p WHERE p.course_id = ? AND p.student_id = u.id) AS participaciones
        FROM enrollments e
        JOIN users u ON u.id = e.student_id
        LEFT JOIN attendance_sessions s ON s.course_id = e.course_id
        LEFT JOIN attendance_records ar ON ar.session_id = s.id AND ar.student_id = u.id
        WHERE e.course_id = ?
        GROUP BY u.id, u.full_name, u.email
        ORDER BY u.full_name ASC
    ');
    $stuStmt->execute([$cid, $cid]);
    $studentStats = $stuStmt->fetchAll();

    $totalPresentesYRetardos = 0;
    $totalEsperadas = count($studentStats) * $totalSessions;
    foreach ($studentStats as &$st) {
        $p = (int)$st['presentes'];
        $r = (int)$st['retardos'];
        $totalPresentesYRetardos += ($p + $r);
        $st['pct'] = $totalSessions > 0 ? round((($p + $r) / $totalSessions) * 100, 1) : 0.0;
    }
    unset($st);

    $metrics['sessions'] = $totalSessions;
    $metrics['participations'] = $totalPart;
    $metrics['attendance_pct'] = $totalEsperadas > 0 ? round(($totalPresentesYRetardos / $totalEsperadas) * 100, 1) : 0.0;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Dashboard Profesor - SGAP</title>
  <link rel="stylesheet" href="/sgap/assets/css/style.css">
</head>
<body>
  <nav class="navbar">
    <div class="brand">SGAP · Profesor</div>
    <div class="nav-links">
      <a href="/sgap/profesor/dashboard.php"><strong>Dashboard</strong></a>
      <a href="/sgap/profesor/courses.php">Cursos</a>
      <span class="muted"><?= e($user['full_name']) ?></span>
      <a href="/sgap/logout.php" class="btn btn-secondary btn-sm">Cerrar sesión</a>
    </div>
  </nav>

  <div class="container">
    <h1>Dashboard del Profesor</h1>

    <?php if (isset($_GET['export_error'])): ?>
      <div class="alert alert-warning">No hay datos suficientes para exportar</div>
    <?php endif; ?>

    <?php if (empty($courses)): ?>
      <div class="alert alert-info">Aún no tienes cursos registrados/inscritos</div>
      <a href="/sgap/profesor/courses.php" class="btn btn-primary">Crear mi primer curso</a>
    <?php else: ?>
      <div class="card">
        <form method="GET" class="actions-row">
          <label for="course_id"><strong>Curso:</strong></label>
          <select id="course_id" name="course_id" class="form-control" style="max-width:340px;" onchange="this.form.submit()">
            <?php foreach ($courses as $c): ?>
              <option value="<?= (int)$c['id'] ?>" <?= (int)$c['id'] === $selectedCourseId ? 'selected' : '' ?>>
                <?= e($c['code']) ?> - <?= e($c['name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
          <a href="/sgap/profesor/attendance.php?course_id=<?= $selectedCourseId ?>" class="btn btn-primary btn-sm">Pase de lista</a>
          <a href="/sgap/profesor/participation.php?course_id=<?= $selectedCourseId ?>" class="btn btn-success btn-sm">Participación</a>
          <a href="/sgap/profesor/export_csv.php?course_id=<?= $selectedCourseId ?>" class="btn btn-secondary btn-sm">Exportar CSV</a>
        </form>
      </div>

      <div class="grid-3">
        <div class="stat-box">
          <div class="stat-label">Sesiones Impartidas</div>
          <div class="stat-value"><?= $metrics['sessions'] ?></div>
        </div>
        <div class="stat-box">
          <div class="stat-label">Asistencia del Grupo</div>
          <div class="stat-value"><?= number_format($metrics['attendance_pct'], 1) ?>%</div>
        </div>
        <div class="stat-box">
          <div class="stat-label">Participaciones del Grupo</div>
          <div class="stat-value"><?= $metrics['participations'] ?></div>
        </div>
      </div>

      <div class="card">
        <h2>Resumen por Estudiante</h2>
        <?php if (empty($studentStats)): ?>
          <p class="muted">No hay estudiantes inscritos en este curso.</p>
        <?php else: ?>
          <table>
            <thead>
              <tr>
                <th>Estudiante</th>
                <th>Correo</th>
                <th>Presentes</th>
                <th>Faltas</th>
                <th>Retardos</th>
                <th>% Asistencia</th>
                <th>Participaciones</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($studentStats as $st): ?>
                <tr>
                  <td><?= e($st['full_name']) ?></td>
                  <td><?= e($st['email']) ?></td>
                  <td><?= (int)$st['presentes'] ?></td>
                  <td><?= (int)$st['faltas'] ?></td>
                  <td><?= (int)$st['retardos'] ?></td>
                  <td><strong><?= number_format((float)$st['pct'], 1) ?>%</strong></td>
                  <td><?= (int)$st['participaciones'] ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </div>
</body>
</html>
