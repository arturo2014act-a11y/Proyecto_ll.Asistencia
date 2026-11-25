<?php
declare(strict_types=1);

function validateFullName(string $name): ?string {
    $name = trim($name);
    if ($name === '') {
        return 'Este campo es obligatorio';
    }
    $len = mb_strlen($name, 'UTF-8');
    if ($len > 80) {
        return 'Se superó el máximo de caracteres';
    }
    if ($len < 2 || !preg_match("/^[\p{L}\s\-']+$/u", $name)) {
        return 'Debe tener entre 2 y 80 caracteres (letras, acentos, espacios, guion y apóstrofo).';
    }
    return null;
}

function validateEmail(string $email): ?string {
    $email = trim($email);
    if ($email === '') {
        return 'Este campo es obligatorio';
    }
    if (mb_strlen($email, 'UTF-8') > 100) {
        return 'Se superó el máximo de caracteres';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return 'Introduce un correo válido';
    }
    return null;
}

function validatePassword(string $password): ?string {
    if ($password === '') {
        return 'Este campo es obligatorio';
    }
    $len = mb_strlen($password, 'UTF-8');
    if ($len < 8 || $len > 10) {
        return 'La contraseña debe tener de 8 a 10 caracteres';
    }
    return null;
}

function validateCourseData(string $name, string $group, string $description): array {
    $errors = [];
    $name = trim($name);
    $group = trim($group);
    $description = trim($description);

    if ($name === '') {
        $errors['name'] = 'Este campo es obligatorio';
    } else {
        $nameLen = mb_strlen($name, 'UTF-8');
        if ($nameLen > 80) {
            $errors['name'] = 'Se superó el máximo de caracteres';
        } elseif ($nameLen < 3 || !preg_match('/^[\p{L}\p{N}\s\-]+$/u', $name)) {
            $errors['name'] = 'El nombre debe tener entre 3 y 80 caracteres (letras, números, espacios, acentos y guion).';
        }
    }

    if ($group === '') {
        $errors['group_name'] = 'Este campo es obligatorio';
    } else {
        $groupLen = mb_strlen($group, 'UTF-8');
        if ($groupLen > 10) {
            $errors['group_name'] = 'Se superó el máximo de caracteres';
        } elseif ($groupLen < 1 || !preg_match('/^[a-zA-Z0-9\-]+$/', $group)) {
            $errors['group_name'] = 'El grupo debe tener entre 1 y 10 caracteres alfanuméricos o guion.';
        }
    }

    if ($description !== '' && mb_strlen($description, 'UTF-8') > 200) {
        $errors['description'] = 'Se superó el máximo de caracteres';
    }

    return $errors;
}
