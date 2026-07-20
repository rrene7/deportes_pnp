<?php

require dirname(__DIR__) . '/src/bootstrap.php';

$user = require_permission($pdo, 'event.manage');

$event = $pdo->query(
    "SELECT *
     FROM eventos
     WHERE slug = 'carrera-5k-policia-2026'
     LIMIT 1"
)->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $hour = trim((string) ($_POST['hora_salida'] ?? '')) ?: null;
    $price = trim((string) ($_POST['precio'] ?? ''));
    $capacity = trim((string) ($_POST['cupos'] ?? ''));

    $stmt = $pdo->prepare(
        'UPDATE eventos
         SET nombre = ?,
             fecha_evento = ?,
             hora_salida = ?,
             hora_confirmada = ?,
             lugar_salida = ?,
             precio = ?,
             cupos = ?,
             inscripciones_abiertas = ?,
             yappy_numero = ?,
             entrega_kit_texto = ?,
             descripcion = ?
         WHERE id = ?'
    );

    $stmt->execute([
        trim((string) $_POST['nombre']),
        (string) $_POST['fecha_evento'],
        $hour,
        isset($_POST['hora_confirmada']) ? 1 : 0,
        trim((string) $_POST['lugar_salida']) ?: null,
        $price === '' ? null : (float) $price,
        $capacity === '' ? null : (int) $capacity,
        isset($_POST['inscripciones_abiertas']) ? 1 : 0,
        trim((string) $_POST['yappy_numero']),
        trim((string) $_POST['entrega_kit_texto']),
        trim((string) $_POST['descripcion']),
        $event['id'],
    ]);

    audit(
        $pdo,
        (int) $user['id'],
        'actualizar_evento',
        'evento',
        (int) $event['id']
    );

    flash('success', 'Configuración guardada.');
    redirect('admin/evento.php');
}

$pageTitle = 'Configurar evento';
require __DIR__ . '/_header.php';
?>

<div class="admin-heading">
    <div>
        <span class="section-kicker">Configuración</span>
        <h1>Datos del evento</h1>
        <p>Los cambios se reflejan inmediatamente en la página pública.</p>
    </div>
</div>

<section class="card">
    <form method="post" class="form-grid">
        <?= csrf_field() ?>

        <label class="full">
            Nombre del evento
            <input name="nombre" value="<?= e($event['nombre']) ?>" required>
        </label>

        <label>
            Fecha
            <input
                type="date"
                name="fecha_evento"
                value="<?= e($event['fecha_evento']) ?>"
                required
            >
        </label>

        <label>
            Hora de salida
            <input
                type="time"
                name="hora_salida"
                value="<?= e(
                    $event['hora_salida']
                        ? substr($event['hora_salida'], 0, 5)
                        : ''
                ) ?>"
            >
        </label>

        <label class="check-label">
            <input
                type="checkbox"
                name="hora_confirmada"
                <?= $event['hora_confirmada'] ? 'checked' : '' ?>
            >
            <span>Publicar la hora como confirmada</span>
        </label>

        <label>
            Lugar de salida
            <input
                name="lugar_salida"
                value="<?= e($event['lugar_salida']) ?>"
            >
        </label>

        <label>
            Precio de inscripción
            <input
                type="number"
                step="0.01"
                min="0"
                name="precio"
                value="<?= e($event['precio']) ?>"
                placeholder="Dejar vacío si está por confirmar"
            >
        </label>

        <label>
            Cantidad máxima de cupos
            <input
                type="number"
                min="1"
                name="cupos"
                value="<?= e($event['cupos']) ?>"
                placeholder="Sin límite"
            >
        </label>

        <label>
            Número de Yappy
            <input
                name="yappy_numero"
                value="<?= e($event['yappy_numero']) ?>"
                required
            >
        </label>

        <label class="full">
            Entrega de kits
            <input
                name="entrega_kit_texto"
                value="<?= e($event['entrega_kit_texto']) ?>"
                required
            >
        </label>

        <label class="full">
            Descripción
            <textarea name="descripcion" rows="4"><?= e(
                $event['descripcion']
            ) ?></textarea>
        </label>

        <label class="check-label full">
            <input
                type="checkbox"
                name="inscripciones_abiertas"
                <?= $event['inscripciones_abiertas'] ? 'checked' : '' ?>
            >
            <span>Mantener inscripciones abiertas</span>
        </label>

        <button class="btn full" type="submit">
            Guardar configuración
        </button>
    </form>
</section>

<?php require __DIR__ . '/_footer.php'; ?>

