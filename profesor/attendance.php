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

$sessionDate = $_GET['date'] ?? $_POST['session_date'] ?? date('Y-m-d');
$success = '';
$error = '';

// Estudiantes inscritos
$stuStmt = $pdo->prepare('
    SELECT u.id, u.full_name, u.email
    FROM enrollments e
    JOIN users u ON u.id = e.student_id
    WHERE e.course_id = ?
    ORDER BY u.full_name ASC
');
$stuStmt->execute([$courseId]);
$students = $stuStmt->fetchAll();

// Obtener o crear sesión según acción
$sessStmt = $pdo->prepare('SELECT * FROM attendance_sessions WHERE course_id = ? AND session_date = ? LIMIT 1');
$sessStmt->execute([$courseId, $sessionDate]);
$session = $sessStmt->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if (empty($students)) {
        $error = 'No hay estudiantes inscritos en este curso.';
    } elseif ($action === 'save_manual') {
        $statuses = $_POST['status'] ?? [];
        $validStatuses = ['Presente', 'Falta', 'Retardo'];
        $allValid = true;
        foreach ($students as $s) {
            $st = $statuses[$s['id']] ?? '';
            if (!in_array($st, $validStatuses, true)) {
                $allValid = false;
                break;
            }
        }
        if (!$allValid) {
            $error = 'Todos los estudiantes inscritos deben tener un estado válido seleccionado.';
        } else {
            $pdo->beginTransaction();
            try {
                if (!$session) {
                    $insSess = $pdo->prepare('INSERT INTO attendance_sessions (course_id, session_date) VALUES (?, ?)');
                    $insSess->execute([$courseId, $sessionDate]);
                    $sessionId = (int)$pdo->lastInsertId();
                } else {
                    $sessionId = (int)$session['id'];
                }
                $upsert = $pdo->prepare('
                    INSERT INTO attendance_records (session_id, student_id, status)
                    VALUES (?, ?, ?)
                    ON DUPLICATE KEY UPDATE status = VALUES(status)
                ');
                foreach ($students as $s) {
                    $upsert->execute([$sessionId, $s['id'], $statuses[$s['id']]]);
                }
                $pdo->commit();
                $success = 'Asistencia guardada correctamente para el ' . $sessionDate . '.';
                $sessStmt->execute([$courseId, $sessionDate]);
                $session = $sessStmt->fetch();
            } catch (Throwable $ex) {
                $pdo->rollBack();
                $error = 'Error al guardar la asistencia.';
            }
        }
    } elseif ($action === 'start_qr') {
        $token = bin2hex(random_bytes(16));
        $pdo->beginTransaction();
        try {
            if (!$session) {
                $insSess = $pdo->prepare('INSERT INTO attendance_sessions (course_id, session_date, qr_token, is_qr_open) VALUES (?, ?, ?, 1)');
                $insSess->execute([$courseId, $sessionDate, $token]);
                $sessionId = (int)$pdo->lastInsertId();
            } else {
                $sessionId = (int)$session['id'];
                $updSess = $pdo->prepare('UPDATE attendance_sessions SET qr_token = COALESCE(qr_token, ?), is_qr_open = 1 WHERE id = ?');
                $updSess->execute([$token, $sessionId]);
            }
            // Inicializar todos con Falta por defecto si no tienen registro
            $insRec = $pdo->prepare('INSERT IGNORE INTO attendance_records (session_id, student_id, status) VALUES (?, ?, "Falta")');
            foreach ($students as $s) {
                $insRec->execute([$sessionId, $s['id']]);
            }
            $pdo->commit();
            $success = 'Sesión QR iniciada. Los estudiantes ya pueden escanear el código.';
            $sessStmt->execute([$courseId, $sessionDate]);
            $session = $sessStmt->fetch();
        } catch (Throwable $ex) {
            $pdo->rollBack();
            $error = 'Error al iniciar la sesión QR.';
        }
    } elseif ($action === 'close_qr' && $session) {
        $upd = $pdo->prepare('UPDATE attendance_sessions SET is_qr_open = 0 WHERE id = ?');
        $upd->execute([$session['id']]);
        $success = 'Sesión QR cerrada. Ahora puedes ajustar manualmente cualquier estado si lo necesitas.';
        $sessStmt->execute([$courseId, $sessionDate]);
        $session = $sessStmt->fetch();
    }
}

$recordsMap = [];
if ($session) {
    $recStmt = $pdo->prepare('SELECT student_id, status FROM attendance_records WHERE session_id = ?');
    $recStmt->execute([$session['id']]);
    foreach ($recStmt->fetchAll() as $r) {
        $recordsMap[(int)$r['student_id']] = $r['status'];
    }
}

$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$qrUrl = ($session && !empty($session['qr_token']))
    ? $scheme . '://' . $host . '/sgap/qr_checkin.php?token=' . $session['qr_token']
    : '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Asistencia - SGAP</title>
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
      <h1>Asistencia: <?= e($course['name']) ?> (<?= e($course['code']) ?>)</h1>
      <a href="/sgap/profesor/courses.php" class="btn btn-secondary btn-sm">Volver a cursos</a>
    </div>

    <?php if ($error !== ''): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
    <?php if ($success !== ''): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>

    <div class="card">
      <form method="GET" class="actions-row">
        <input type="hidden" name="course_id" value="<?= $courseId ?>">
        <label for="date"><strong>Fecha de sesión:</strong></label>
        <input type="date" id="date" name="date" value="<?= e($sessionDate) ?>" class="form-control" style="max-width:180px;">
        <button type="submit" class="btn btn-secondary btn-sm">Cargar fecha</button>
      </form>
    </div>

    <div class="card">
      <div class="card-header">
        <h2>Asistencia por Código QR (RF-09)</h2>
        <?php if (!$session || !$session['is_qr_open']): ?>
          <form method="POST">
            <input type="hidden" name="action" value="start_qr">
            <input type="hidden" name="course_id" value="<?= $courseId ?>">
            <input type="hidden" name="session_date" value="<?= e($sessionDate) ?>">
            <button type="submit" class="btn btn-primary btn-sm">Iniciar sesión QR</button>
          </form>
        <?php else: ?>
          <form method="POST">
            <input type="hidden" name="action" value="close_qr">
            <input type="hidden" name="course_id" value="<?= $courseId ?>">
            <input type="hidden" name="session_date" value="<?= e($sessionDate) ?>">
            <button type="submit" class="btn btn-danger btn-sm">Cerrar sesión QR</button>
          </form>
        <?php endif; ?>
      </div>

      <?php if ($session && $session['is_qr_open'] && $qrUrl !== ''): ?>
        <div class="qr-box">
          <p><strong>Sesión QR Activa</strong> — Escanea este código para registrar tu asistencia:</p>
          <div id="qrcode" style="margin:1rem auto; display:inline-block; background:#fff; padding:10px; border-radius:8px;"></div>
          <p class="muted" style="word-break:break-all; font-size:0.82rem;">URL: <a href="<?= e($qrUrl) ?>" target="_blank"><?= e($qrUrl) ?></a></p>
        </div>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
        <script>
          if (typeof QRCode !== 'undefined') {
            new QRCode(document.getElementById('qrcode'), { text: <?= json_encode($qrUrl) ?>, width: 180, height: 180 });
          } else {
            document.getElementById('qrcode').innerHTML = '<p class="muted">Usa el enlace de abajo para registrar asistencia.</p>';
          }
        </script>
      <?php elseif ($session && !$session['is_qr_open'] && $qrUrl !== ''): ?>
        <p class="muted">La sesión QR para esta fecha está cerrada.</p>
      <?php else: ?>
        <p class="muted">Inicia una sesión QR para generar el código único de esta fecha.</p>
      <?php endif; ?>
    </div>

    <div class="card">
      <h2>Pase de Lista Manual (RF-08)</h2>
      <?php if (empty($students)): ?>
        <p class="muted">No hay estudiantes inscritos en este curso.</p>
      <?php else: ?>
        <form method="POST">
          <input type="hidden" name="action" value="save_manual">
          <input type="hidden" name="course_id" value="<?= $courseId ?>">
          <input type="hidden" name="session_date" value="<?= e($sessionDate) ?>">
          <table>
            <thead>
              <tr><th>Estudiante</th><th>Correo</th><th>Estado</th></tr>
            </thead>
            <tbody>
              <?php foreach ($students as $s): $curr = $recordsMap[(int)$s['id']] ?? ''; ?>
                <tr>
                  <td><?= e($s['full_name']) ?></td>
                  <td><?= e($s['email']) ?></td>
                  <td>
                    <select name="status[<?= (int)$s['id'] ?>]" class="form-control" style="max-width:160px;" required>
                      <option value="">-- Seleccionar --</option>
                      <option value="Presente" <?= $curr === 'Presente' ? 'selected' : '' ?>>Presente</option>
                      <option value="Falta" <?= $curr === 'Falta' ? 'selected' : '' ?>>Falta</option>
                      <option value="Retardo" <?= $curr === 'Retardo' ? 'selected' : '' ?>>Retardo</option>
                    </select>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
          <div style="margin-top:1rem;">
            <button type="submit" class="btn btn-success">Guardar asistencia</button>
          </div>
        </form>
      <?php endif; ?>
    </div>
  </div>
</body>
</html>
