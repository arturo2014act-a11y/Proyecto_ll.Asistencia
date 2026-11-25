<?php
declare(strict_types=1);

function validateFullName(string $name): ?string {
    $name = trim($name);
    $len = mb_strlen($name, 'UTF-8');
    if ($len < 3 || $len > 80) {
        return 'El nombre completo debe tener entre 3 y 80 caracteres.';
    }
    if (!preg_match('/^[\p{L}\s]+$/u', $name)) {
        return 'El nombre completo solo puede contener letras y espacios.';
    }
    return null;
}

function validateEmail(string $email): ?string {
    $email = trim($email);
    if ($email === '' || mb_strlen($email, 'UTF-8') > 100) {
        return 'El correo electrónico es obligatorio y debe tener máximo 100 caracteres.';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return 'El formato del correo electrónico no es válido (usuario@dominio.com).';
    }
    return null;
}

function validatePassword(string $password): ?string {
    if (strlen($password) < 8) {
        return 'La contraseña debe tener al menos 8 caracteres.';
    }
    if (preg_match('/\s/', $password)) {
        return 'La contraseña no puede contener espacios en blanco.';
    }
    if (!preg_match('/[A-Z]/', $password) || !preg_match('/[a-z]/', $password) || !preg_match('/[0-9]/', $password)) {
        return 'La contraseña debe incluir al menos 1 mayúscula, 1 minúscula y 1 número.';
    }
    return null;
}

function validateCourseData(string $name, string $code, string $schedule): array {
    $errors = [];
    $name = trim($name);
    $code = trim($code);
    $schedule = trim($schedule);

    $nameLen = mb_strlen($name, 'UTF-8');
    if ($nameLen < 3 || $nameLen > 80 || !preg_match('/^[\p{L}\p{N}\s]+$/u', $name)) {
        $errors['name'] = 'El nombre del curso debe tener entre 3 y 80 caracteres alfanuméricos y espacios.';
    }

    $codeLen = mb_strlen($code, 'UTF-8');
    if ($codeLen < 2 || $codeLen > 15 || !preg_match('/^[a-zA-Z0-9]+$/', $code)) {
        $errors['code'] = 'La clave/grupo debe tener entre 2 y 15 caracteres alfanuméricos sin espacios.';
    }

    $schedLen = mb_strlen($schedule, 'UTF-8');
    if ($schedLen < 5 || $schedLen > 50) {
        $errors['schedule'] = 'El horario debe tener entre 5 y 50 caracteres (ej. Lun-Mié 10:00-12:00).';
    }

    return $errors;
}
