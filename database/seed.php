<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';

$pdo = new PDO('mysql:host=' . DB_HOST . ';charset=utf8mb4', DB_USER, DB_PASS, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);

// Drop and recreate tables cleanly if needed for v3.1 migration
$sql = file_get_contents(__DIR__ . '/schema.sql');
$pdo->exec("DROP DATABASE IF EXISTS " . DB_NAME);
$pdo->exec($sql);

$db = getDB();

// Seed initial accounts (RF-01: mínimo un profesor y dos estudiantes, contraseñas 8-10 caracteres)
$users = [
    [
        'full_name' => 'Dr. Carlos Méndez',
        'email' => 'profesor@tecnm.mx',
        'password' => 'Prof1234',
        'role' => 'profesor',
        'control_number' => null,
    ],
    [
        'full_name' => 'Ana Sofía Pérez',
        'email' => 'ana.perez@tecnm.mx',
        'password' => 'Estu1234',
        'role' => 'estudiante',
        'control_number' => '09210001',
    ],
    [
        'full_name' => 'Luis Fernando Ku',
        'email' => 'luis.ku@tecnm.mx',
        'password' => 'Estu1234',
        'role' => 'estudiante',
        'control_number' => '21080042',
    ],
    [
        'full_name' => 'María José Chan',
        'email' => 'maria.chan@tecnm.mx',
        'password' => 'Estu1234',
        'role' => 'estudiante',
        'control_number' => '21080105',
    ],
];

$stmt = $db->prepare('INSERT INTO users (full_name, email, password_hash, role, control_number) VALUES (?, ?, ?, ?, ?)');
foreach ($users as $u) {
    $hash = password_hash($u['password'], PASSWORD_BCRYPT);
    $stmt->execute([$u['full_name'], $u['email'], $hash, $u['role'], $u['control_number']]);
}

echo "Base de datos sgap_db inicializada y cuentas de prueba cargadas.\n";
