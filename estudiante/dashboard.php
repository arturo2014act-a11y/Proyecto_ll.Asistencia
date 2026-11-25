<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';

requireRole('estudiante');
$user = currentUser();
$pdo = getDB();

// RF-13: Bloquear cualquier intento de consultar otro estudiante por URL
if (isset($_GET['student_id']) && (int)$_GET['student_id'] !== $user['id']) {
    http_response_code(403);
    die('Acceso denegado: no puedes consultar el historial de otro estudiante.');
}

$coursesStmt = $pdo->prepare('
    SELECT c.*, u.full_name AS professor_name
    FROM enrollments e
    JOIN courses c ON c.id = e.course_id
    JOIN users u ON u.id = c.professor_id
    WHERE e.student_id = ?
    ORDER BY c.name ASC
');
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

$records = [];
$summary = ['Presente' => 0, 'Falta' => 0, 'Retardo' => 0, 'pct' => 0.0, 'participations' => 0];

if ($selectedCourse) {
    $recStmt = $pdo->prepare('
        SELECT s.session_date, COALESCE(ar.status, "Falta") AS status
        FROM attendance_sessions s
        LEFT JOIN attendance_records ar ON ar.session_id = s.id AND ar.student_id = ?
        WHERE s.course_id = ?
        ORDER BY s.session_date DESC
    ');
    $recStmt->execute([$user['id'], $selectedCourseId]);
    $records = $recStmt->fetchAll();

    foreach ($records as $r) {
        if (isset($summary[$r['status']])) {
            $summary[$r['status']]++;
        }
    }
    $totalSess = count($records);
    $summary['pct'] = $totalSess > 0 ? round((($summary['Presente'] + $summary['Retardo']) / $totalSess) * 100, 1) : 0.0;

    $partStmt = $pdo->prepare('SELECT COUNT(*) FROM participations WHERE course_id = ? AND student_id = ?');
    $partStmt->execute([$selectedCourseId, $user['id']]);
    $summary['participations'] = (int)$partStmt->fetchColumn();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Mi Historial - SGAP</title>
  <link rel="stylesheet" href="/sgap/assets/css/style.css">
</head>
<body>
  <nav class="navbar">
    <div class="brand">SGAP · Estudiante</div>
    <div class="nav-links">
      <span class="muted"><?= e($user['full_name']) ?></span>
      <a href="/sgap/logout.php" class="btn btn-secondary btn-sm">Cerrar sesión</a>
    </div>
  </nav>

  <div class="container">
    <h1>Mi Desempeño Académico</h1>

    <?php if (empty($courses)): ?>
      <div class="alert alert-info">Aún no tienes cursos registrados/inscritos</div>
    <?php else: ?>
      <div class="card">
        <form method="GET" class="actions-row">
          <label for="course_id"><strong>Curso:</strong></label>
          <select id="course_id" name="course_id" class="form-control" style="max-width:360px;" onchange="this.form.submit()">
            <?php foreach ($courses as $c): ?>
              <option value="<?= (int)$c['id'] ?>" <?= (int)$c['id'] === $selectedCourseId ? 'selected' : '' ?>>
                <?= e($c['code']) ?> - <?= e($c['name']) ?> (Prof. <?= e($c['professor_name']) ?>)
              </option>
            <?php endforeach; ?>
          </select>
        </form>
      </div>

      <div class="grid-3">
        <div class="stat-box">
          <div class="stat-label">% Asistencia</div>
          <div class="stat-value"><?= number_format($summary['pct'], 1) ?>%</div>
        </div>
        <div class="stat-box">
          <div class="stat-label">Presentes / Retardos / Faltas</div>
          <div class="stat-value" style="font-size:1.3rem;">
            <?= $summary['Presente'] ?> / <?= $summary['Retardo'] ?> / <?= $summary['Falta'] ?>
          </div>
        </div>
        <div class="stat-box">
          <div class="stat-label">Total Participaciones</div>
          <div class="stat-value"><?= $summary['participations'] ?></div>
        </div>
      </div>

      <div class="card">
        <h2>Historial de Asistencias</h2>
        <?php if (empty($records)): ?>
          <p class="muted">Aún no hay sesiones registradas en este curso.</p>
        <?php else: ?>
          <table>
            <thead>
              <tr><th>Fecha</th><th>Estado</th></tr>
            </thead>
            <tbody>
              <?php foreach ($records as $r): ?>
                <tr>
                  <td><?= e($r['session_date']) ?></td>
                  <td>
                    <span class="badge badge-<?= strtolower($r['status']) ?>"><?= e($r['status']) ?></span>
                  </td>
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
