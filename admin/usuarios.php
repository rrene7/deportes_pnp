<?php

require dirname(__DIR__) . '/src/bootstrap.php';

$currentUser = require_permission($pdo, 'users.manage');
$pageTitle = 'Administración de usuarios';

$roles = admin_role_labels();
$allowedRoles = array_keys($roles);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $action = (string) ($_POST['accion'] ?? '');
    $id = (int) ($_POST['id'] ?? 0);
    $name = trim((string) ($_POST['nombre'] ?? ''));
    $username = trim((string) ($_POST['usuario'] ?? ''));
    $role = (string) ($_POST['rol'] ?? '');
    $active = isset($_POST['activo']) ? 1 : 0;
    $password = (string) ($_POST['password'] ?? '');
    $passwordConfirmation = (string) ($_POST['password_confirmacion'] ?? '');

    try {
        if ($name === '') {
            throw new RuntimeException('El nombre es obligatorio.');
        }

        if (!preg_match('/^[A-Za-z0-9._-]{3,80}$/', $username)) {
            throw new RuntimeException(
                'El usuario debe tener entre 3 y 80 caracteres y usar letras, números, punto, guion o guion bajo.'
            );
        }

        if (!in_array($role, $allowedRoles, true)) {
            throw new RuntimeException('El rol seleccionado no es válido.');
        }

        if ($action === 'crear') {
            if (strlen($password) < 8) {
                throw new RuntimeException('La contraseña debe tener al menos 8 caracteres.');
            }

            if (!hash_equals($password, $passwordConfirmation)) {
                throw new RuntimeException('Las contraseñas no coinciden.');
            }

            $stmt = $pdo->prepare(
                'INSERT INTO usuarios
                    (nombre, usuario, password_hash, rol, activo)
                 VALUES (?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $name,
                $username,
                password_hash($password, PASSWORD_DEFAULT),
                $role,
                $active,
            ]);

            $newId = (int) $pdo->lastInsertId();

            audit(
                $pdo,
                (int) $currentUser['id'],
                'crear_usuario',
                'usuario',
                $newId,
                [
                    'usuario' => $username,
                    'rol' => $role,
                    'activo' => (bool) $active,
                    'autenticacion' => 'local',
                ]
            );

            flash('success', 'Usuario creado correctamente.');
            redirect('admin/usuarios.php');
        }

        if ($action === 'actualizar') {
            $stmt = $pdo->prepare(
                'SELECT id, nombre, usuario, rol, activo
                 FROM usuarios
                 WHERE id = ?
                 LIMIT 1'
            );
            $stmt->execute([$id]);
            $existing = $stmt->fetch();

            if (!$existing) {
                throw new RuntimeException('El usuario no existe.');
            }

            if ((int) $existing['id'] === (int) $currentUser['id']) {
                if (!$active) {
                    throw new RuntimeException('No puede desactivar su propia cuenta.');
                }

                if ($role !== 'administrador') {
                    throw new RuntimeException('No puede quitarse su propio rol de administrador.');
                }
            }

            $removesActiveAdministrator = (
                $existing['rol'] === 'administrador'
                && (bool) $existing['activo']
                && ($role !== 'administrador' || !$active)
            );

            if ($removesActiveAdministrator) {
                $countStmt = $pdo->prepare(
                    "SELECT COUNT(*)
                     FROM usuarios
                     WHERE rol = 'administrador'
                       AND activo = 1
                       AND id <> ?"
                );
                $countStmt->execute([$id]);

                if ((int) $countStmt->fetchColumn() < 1) {
                    throw new RuntimeException(
                        'Debe permanecer al menos un administrador activo.'
                    );
                }
            }

            $passwordChanged = false;

            if ($password !== '') {
                if (strlen($password) < 8) {
                    throw new RuntimeException(
                        'La nueva contraseña debe tener al menos 8 caracteres.'
                    );
                }

                if (!hash_equals($password, $passwordConfirmation)) {
                    throw new RuntimeException('Las contraseñas no coinciden.');
                }

                $update = $pdo->prepare(
                    'UPDATE usuarios
                     SET nombre = ?,
                         usuario = ?,
                         rol = ?,
                         activo = ?,
                         password_hash = ?
                     WHERE id = ?'
                );
                $update->execute([
                    $name,
                    $username,
                    $role,
                    $active,
                    password_hash($password, PASSWORD_DEFAULT),
                    $id,
                ]);
                $passwordChanged = true;
            } else {
                $update = $pdo->prepare(
                    'UPDATE usuarios
                     SET nombre = ?,
                         usuario = ?,
                         rol = ?,
                         activo = ?
                     WHERE id = ?'
                );
                $update->execute([
                    $name,
                    $username,
                    $role,
                    $active,
                    $id,
                ]);
            }

            audit(
                $pdo,
                (int) $currentUser['id'],
                'actualizar_usuario',
                'usuario',
                $id,
                [
                    'usuario_anterior' => $existing['usuario'],
                    'usuario_nuevo' => $username,
                    'rol_anterior' => $existing['rol'],
                    'rol_nuevo' => $role,
                    'activo_anterior' => (bool) $existing['activo'],
                    'activo_nuevo' => (bool) $active,
                    'contrasena_restaurada' => $passwordChanged,
                ]
            );

            if ($id === (int) $currentUser['id']) {
                sync_admin_session($pdo);
            }

            flash('success', 'Usuario actualizado correctamente.');
            redirect('admin/usuarios.php');
        }

        throw new RuntimeException('Acción no válida.');
    } catch (PDOException $exception) {
        if ((string) $exception->getCode() === '23000') {
            flash('danger', 'El nombre de usuario ya está en uso.');
        } else {
            flash('danger', 'No fue posible guardar el usuario.');
        }

        redirect(
            $action === 'actualizar' && $id > 0
                ? 'admin/usuarios.php?editar=' . $id
                : 'admin/usuarios.php'
        );
    } catch (Throwable $exception) {
        flash('danger', $exception->getMessage());

        redirect(
            $action === 'actualizar' && $id > 0
                ? 'admin/usuarios.php?editar=' . $id
                : 'admin/usuarios.php'
        );
    }
}

$editUser = null;
$editId = (int) ($_GET['editar'] ?? 0);

if ($editId > 0) {
    $stmt = $pdo->prepare(
        'SELECT id, nombre, usuario, rol, activo, creado_en
         FROM usuarios
         WHERE id = ?
         LIMIT 1'
    );
    $stmt->execute([$editId]);
    $editUser = $stmt->fetch() ?: null;
}

$users = $pdo->query(
    'SELECT
        u.id,
        u.nombre,
        u.usuario,
        u.rol,
        u.activo,
        u.creado_en,
        (
            SELECT MAX(a.creado_en)
            FROM auditoria a
            WHERE a.usuario_id = u.id
              AND a.accion = "inicio_sesion"
        ) AS ultimo_acceso
     FROM usuarios u
     ORDER BY u.activo DESC, u.nombre ASC'
)->fetchAll();

require __DIR__ . '/_header.php';
?>

<div class="admin-heading">
    <div>
        <span class="section-kicker">Seguridad y acceso</span>
        <h1>Usuarios del sistema</h1>
        <p>
            Cree cuentas locales y asigne solamente los permisos necesarios.
            Más adelante estas cuentas podrán vincularse al dominio institucional.
        </p>
    </div>
</div>

<section class="roles-summary">
    <?php foreach ($roles as $roleKey => $roleName): ?>
        <article class="role-summary-card">
            <span class="role-chip role-<?= e($roleKey) ?>">
                <?= e($roleName) ?>
            </span>
            <p>
                <?php
                echo match ($roleKey) {
                    'administrador' => 'Control total, usuarios, auditoría, evento y exportación.',
                    'pagos' => 'Consulta inscripciones y confirma o rechaza pagos.',
                    'kits' => 'Consulta inscripciones confirmadas y registra la entrega de kits.',
                    default => 'Puede consultar la información, sin modificar registros.',
                };
                ?>
            </p>
        </article>
    <?php endforeach; ?>
</section>

<div class="user-management-grid">
    <section class="card">
        <span class="section-kicker">
            <?= $editUser ? 'Editar cuenta' : 'Nueva cuenta' ?>
        </span>
        <h2><?= $editUser ? 'Actualizar usuario' : 'Crear usuario' ?></h2>

        <form method="post" class="form-grid one-column">
            <?= csrf_field() ?>
            <input
                type="hidden"
                name="accion"
                value="<?= $editUser ? 'actualizar' : 'crear' ?>"
            >
            <input
                type="hidden"
                name="id"
                value="<?= (int) ($editUser['id'] ?? 0) ?>"
            >

            <label>
                Nombre completo
                <input
                    name="nombre"
                    required
                    maxlength="120"
                    value="<?= e($editUser['nombre'] ?? '') ?>"
                >
            </label>

            <label>
                Usuario
                <input
                    name="usuario"
                    required
                    maxlength="80"
                    autocomplete="off"
                    value="<?= e($editUser['usuario'] ?? '') ?>"
                    placeholder="Ejemplo: jperez"
                >
                <small>Solo letras, números, punto, guion y guion bajo.</small>
            </label>

            <label>
                Rol
                <select name="rol" required>
                    <?php foreach ($roles as $value => $label): ?>
                        <option
                            value="<?= e($value) ?>"
                            <?= ($editUser['rol'] ?? 'consulta') === $value
                                ? 'selected'
                                : '' ?>
                        >
                            <?= e($label) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label>
                <?= $editUser
                    ? 'Nueva contraseña (dejar vacía para conservarla)'
                    : 'Contraseña' ?>
                <input
                    type="password"
                    name="password"
                    minlength="8"
                    autocomplete="new-password"
                    <?= $editUser ? '' : 'required' ?>
                >
            </label>

            <label>
                Confirmar contraseña
                <input
                    type="password"
                    name="password_confirmacion"
                    minlength="8"
                    autocomplete="new-password"
                    <?= $editUser ? '' : 'required' ?>
                >
            </label>

            <label class="check-label">
                <input
                    type="checkbox"
                    name="activo"
                    value="1"
                    <?= !isset($editUser['activo']) || $editUser['activo']
                        ? 'checked'
                        : '' ?>
                >
                <span>Cuenta activa</span>
            </label>

            <div class="user-form-actions">
                <button class="btn" type="submit">
                    <?= $editUser ? 'Guardar cambios' : 'Crear usuario' ?>
                </button>

                <?php if ($editUser): ?>
                    <a class="btn btn-outline" href="usuarios.php">
                        Cancelar edición
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </section>

    <section class="card table-card">
        <div class="table-header">
            <div>
                <h2>Cuentas registradas</h2>
                <p class="muted"><?= count($users) ?> usuarios</p>
            </div>
        </div>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Nombre</th>
                        <th>Usuario</th>
                        <th>Rol</th>
                        <th>Estado</th>
                        <th>Último acceso</th>
                        <th>Acción</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                        <tr>
                            <td>
                                <strong><?= e($user['nombre']) ?></strong>
                                <?php if ((int) $user['id'] === (int) $currentUser['id']): ?>
                                    <small class="current-account">Cuenta actual</small>
                                <?php endif; ?>
                            </td>
                            <td><?= e($user['usuario']) ?></td>
                            <td>
                                <span class="role-chip role-<?= e($user['rol']) ?>">
                                    <?= e(admin_role_label($user['rol'])) ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge <?= $user['activo']
                                    ? 'badge-success'
                                    : 'badge-danger' ?>">
                                    <?= $user['activo'] ? 'Activa' : 'Bloqueada' ?>
                                </span>
                            </td>
                            <td>
                                <?= $user['ultimo_acceso']
                                    ? e(date(
                                        'd/m/Y H:i',
                                        strtotime($user['ultimo_acceso'])
                                    ))
                                    : 'Sin acceso registrado' ?>
                            </td>
                            <td>
                                <a
                                    class="table-link"
                                    href="usuarios.php?editar=<?= (int) $user['id'] ?>"
                                >
                                    Editar
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>

<?php require __DIR__ . '/_footer.php'; ?>

