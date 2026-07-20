<?php

declare(strict_types=1);

function admin_user(): ?array
{
    return $_SESSION['admin_user'] ?? null;
}

function admin_role_labels(): array
{
    return [
        'administrador' => 'Administrador',
        'pagos' => 'Validación de pagos',
        'kits' => 'Entrega de kits',
        'consulta' => 'Solo consulta',
    ];
}

function admin_role_label(string $role): string
{
    return admin_role_labels()[$role] ?? ucfirst($role);
}

function permission_roles(): array
{
    return [
        'dashboard.view' => ['administrador', 'pagos', 'kits', 'consulta'],
        'inscriptions.view' => ['administrador', 'pagos', 'kits', 'consulta'],
        'payments.manage' => ['administrador', 'pagos'],
        'kits.manage' => ['administrador', 'kits'],
        'event.manage' => ['administrador'],
        'export.manage' => ['administrador'],
        'users.manage' => ['administrador'],
        'audit.view' => ['administrador'],
    ];
}

function can_permission(string $permission, ?array $user = null): bool
{
    $user ??= admin_user();
    if (!$user) {
        return false;
    }

    $allowedRoles = permission_roles()[$permission] ?? [];
    return in_array((string) ($user['rol'] ?? ''), $allowedRoles, true);
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

function require_permission(PDO $pdo, string $permission): array
{
    $user = require_admin();

    if (!can_permission($permission, $user)) {
        try {
            audit(
                $pdo,
                (int) $user['id'],
                'acceso_denegado',
                'permiso',
                null,
                ['permiso' => $permission, 'rol' => $user['rol']]
            );
        } catch (Throwable) {
            // La auditoría no debe impedir la navegación.
        }

        flash('danger', 'No tiene permiso para realizar esa acción.');
        redirect('admin/index.php');
    }

    return $user;
}

function sync_admin_session(PDO $pdo): void
{
    $sessionUser = admin_user();
    if (!$sessionUser) {
        return;
    }

    $stmt = $pdo->prepare(
        'SELECT id, nombre, usuario, rol, activo
         FROM usuarios
         WHERE id = ?
         LIMIT 1'
    );
    $stmt->execute([(int) $sessionUser['id']]);
    $databaseUser = $stmt->fetch();

    if (!$databaseUser || !(bool) $databaseUser['activo']) {
        try {
            audit(
                $pdo,
                (int) ($sessionUser['id'] ?? 0) ?: null,
                'sesion_revocada',
                'usuario',
                (int) ($sessionUser['id'] ?? 0) ?: null
            );
        } catch (Throwable) {
        }

        unset($_SESSION['admin_user']);
        return;
    }

    $_SESSION['admin_user'] = [
        'id' => (int) $databaseUser['id'],
        'nombre' => $databaseUser['nombre'],
        'usuario' => $databaseUser['usuario'],
        'rol' => $databaseUser['rol'],
    ];
}

function admin_login(PDO $pdo, string $username, string $password): bool
{
    $stmt = $pdo->prepare(
        'SELECT id, nombre, usuario, password_hash, rol, activo
         FROM usuarios
         WHERE usuario = ?
         LIMIT 1'
    );
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if (
        !$user
        || !(bool) $user['activo']
        || !password_verify($password, $user['password_hash'])
    ) {
        try {
            audit(
                $pdo,
                $user ? (int) $user['id'] : null,
                'inicio_sesion_fallido',
                'autenticacion',
                $user ? (int) $user['id'] : null,
                ['usuario' => $username]
            );
        } catch (Throwable) {
        }

        return false;
    }

    session_regenerate_id(true);
    $_SESSION['admin_user'] = [
        'id' => (int) $user['id'],
        'nombre' => $user['nombre'],
        'usuario' => $user['usuario'],
        'rol' => $user['rol'],
    ];

    try {
        audit(
            $pdo,
            (int) $user['id'],
            'inicio_sesion',
            'usuario',
            (int) $user['id'],
            ['rol' => $user['rol']]
        );
    } catch (Throwable) {
    }

    return true;
}

function admin_logout(?PDO $pdo = null): void
{
    $user = admin_user();

    if ($pdo && $user) {
        try {
            audit(
                $pdo,
                (int) $user['id'],
                'cierre_sesion',
                'usuario',
                (int) $user['id']
            );
        } catch (Throwable) {
        }
    }

    unset($_SESSION['admin_user']);
    session_regenerate_id(true);
}

