<?php

declare(strict_types=1);

function admin_user(): ?array
{
    return $_SESSION['admin_user'] ?? null;
}

function require_admin(): array
{
    $user = admin_user();
    if (!$user) {
        flash('warning', 'Debe iniciar sesión para continuar.');
        redirect('admin/login.php');
    }
    return $user;
}

function admin_login(PDO $pdo, string $username, string $password): bool
{
    $stmt = $pdo->prepare('SELECT id, nombre, usuario, password_hash, rol, activo FROM usuarios WHERE usuario = ? LIMIT 1');
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if (!$user || !(bool)$user['activo'] || !password_verify($password, $user['password_hash'])) {
        return false;
    }

    session_regenerate_id(true);
    $_SESSION['admin_user'] = [
        'id' => (int)$user['id'],
        'nombre' => $user['nombre'],
        'usuario' => $user['usuario'],
        'rol' => $user['rol'],
    ];
    return true;
}

function admin_logout(): void
{
    unset($_SESSION['admin_user']);
    session_regenerate_id(true);
}
