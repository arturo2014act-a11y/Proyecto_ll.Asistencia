<?php
declare(strict_types=1);
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';

$token = trim($_GET['token'] ?? '');
$redirectTarget = '/sgap/qr_checkin.php?token=' . urlencode($token);

// RF-09: Si no hay sesión iniciada, redirige al login y luego regresa aquí
requireLogin($redirectTarget);

$user = currentUser();
$pdo = getDB();

$statusType = 'danger';
$message = '';

if ($user['role'] !== 'estudiante') {
    $message = 'Solo los estudiantes pueden registrar asistencia mediante código QR.';
} elseif ($token === '') {
    $message = 'Código QR inválido.';
} else {
    $sessStmt = $pdo->prepare('
        SELECT s.*, c.name AS course_name, c.code AS course_code
        FROM attendance_sessions s
        JOIN courses c ON c.id = s.course_id
        WHERE s.qr_token = ?
        LIMIT 1
    ');
    $sessStmt->execute([$token]);
    $session = $sessStmt->fetch();

    if (!$session) {
        $message = 'Sesión QR no encontrada.';
    } elseif ((int)$session['is_qr_open'] !== 1) {
        $message = 'La sesión de asistencia ha finalizado';
    } else {
        $enrStmt = $pdo->prepare('SELECT id FROM enrollments WHERE course_id = ? AND student_id = ? LIMIT 1');
        $enrStmt->execute([$session['course_id'], $user['id']]);
        if (!$enrStmt->fetch()) {
            $message = 'No estás inscrito en este curso';
        } else {
            $recStmt = $pdo->prepare('SELECT id, status FROM attendance_records WHERE session_id = ? AND student_id = ? LIMIT 1');
            $recStmt->execute([$session['id'], $user['id']]);
            $record = $recStmt->fetch();

            if ($record && $record['status'] === 'Presente') {
                $statusType = 'warning';
                $message = 'Tu asistencia ya fue registrada en esta sesión';
            } else {
                $upsert = $pdo->prepare('
                    INSERT INTO attendance_records (session_id, student_id, status)
                    VALUES (?, ?, "Presente")
                    ON DUPLICATE KEY UPDATE status = "Presente"
                ');
                $upsert->execute([$session['id'], $user['id']]);
                $statusType = 'success';
                $message = 'Asistencia registrada correctamente';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Registro QR - SGAP</title>
  <link rel="stylesheet" href="/sgap/assets/css/style.css">
</head>
<body>
  <div class="auth-container">
    <div class="card">
      <h1>Registro de Asistencia QR</h1>
      <div class="alert alert-<?= e($statusType) ?>"><?= e($message) ?></div>
      <a href="/sgap/index.php" class="btn btn-primary" style="width:100%;">Ir a mi panel</a>
    </div>
  </div>
</body>
</html>
