<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';

requireRole('profesor');
$user = currentUser();
$pdo = getDB();

$courseId = (int)($_GET['course_id'] ?? 0);
$stmt = $pdo->prepare('SELECT * FROM courses WHERE id = ? AND professor_id = ? LIMIT 1');
$stmt->execute([$courseId, $user['id']]);
$course = $stmt->fetch();
if (!$course) {
    header('Location: /sgap/profesor/dashboard.php');
    exit;
}

$sessStmt = $pdo->prepare('SELECT COUNT(*) FROM attendance_sessions WHERE course_id = ?');
$sessStmt->execute([$courseId]);
$totalSessions = (int)$sessStmt->fetchColumn();

$enrStmt = $pdo->prepare('SELECT COUNT(*) FROM enrollments WHERE course_id = ?');
$enrStmt->execute([$courseId]);
$totalStudents = (int)$enrStmt->fetchColumn();

if ($totalSessions === 0 || $totalStudents === 0) {
    header('Location: /sgap/profesor/dashboard.php?course_id=' . $courseId . '&export_error=1');
    exit;
}

$stuStmt = $pdo->prepare('
    SELECT u.full_name, u.email,
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
$stuStmt->execute([$courseId, $courseId]);
$rows = $stuStmt->fetchAll();

$filename = 'reporte_' . preg_replace('/[^a-zA-Z0-9_-]/', '', $course['code']) . '_' . date('Y-m-d') . '.csv';

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
echo "\xEF\xBB\xBF"; // BOM UTF-8 para Excel

$out = fopen('php://output', 'w');
fputcsv($out, ['Nombre completo', 'Correo', 'Presentes', 'Faltas', 'Retardos', 'Porcentaje de asistencia', 'Total de participaciones']);

foreach ($rows as $r) {
    $p = (int)$r['presentes'];
    $f = (int)$r['faltas'];
    $ret = (int)$r['retardos'];
    $pct = round((($p + $ret) / $totalSessions) * 100, 1) . '%';
    fputcsv($out, [$r['full_name'], $r['email'], $p, $f, $ret, $pct, (int)$r['participaciones']]);
}
fclose($out);
exit;
