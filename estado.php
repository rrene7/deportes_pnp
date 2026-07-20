<?php
require __DIR__ . '/src/bootstrap.php';

$result = null;
$error = null;
$code = strtoupper(trim((string) ($_GET['codigo'] ?? '')));
$last4 = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $code = strtoupper(trim((string) ($_POST['codigo'] ?? '')));
    $last4 = trim((string) ($_POST['ultimos4'] ?? ''));

    $stmt = $pdo->prepare(
        'SELECT
            i.codigo,
            i.estado,
            i.identificacion,
            i.primer_nombre,
            i.primer_apellido,
            c.nombre AS categoria,
            e.nombre AS evento,
            e.fecha_evento,
            e.entrega_kit_texto,
            p.estado AS pago_estado,
            p.observaciones
         FROM inscripciones i
         JOIN categorias c ON c.id = i.categoria_id
         JOIN eventos e ON e.id = i.evento_id
         JOIN pagos p ON p.inscripcion_id = i.id
         WHERE i.codigo = ?
         LIMIT 1'
    );
    $stmt->execute([$code]);
    $row = $stmt->fetch();

    $normalizedIdentification = $row
        ? strtoupper((string) preg_replace('/[^A-Z0-9]/i', '', $row['identificacion']))
        : '';

    $normalizedLookup = strtoupper(
        (string) preg_replace('/[^A-Z0-9]/i', '', $last4)
    );

    if (
        !$row
        || strlen($normalizedLookup) !== 4
        || !str_ends_with($normalizedIdentification, $normalizedLookup)
    ) {
        $error = 'No encontramos una inscripción con esos datos.';
    } else {
        $result = $row;
    }
}
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Consultar inscripción</title>
    <link rel="stylesheet" href="assets/css/app.css">
    <link rel="stylesheet" href="assets/css/sports-theme.css">
</head>
<body class="page-bg">
<main class="container narrow">
    <section class="card">
        <span class="section-kicker">Consulta pública</span>
        <h1>Verificar mi inscripción</h1>

        <p class="muted">
            Ingresa tu número de inscripción y los últimos cuatro caracteres
            de la cédula o pasaporte utilizado en el registro.
        </p>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?= e($error) ?></div>
        <?php endif; ?>

        <form method="post" class="form-grid one-column">
            <?= csrf_field() ?>

            <label>
                Número de inscripción
                <input
                    name="codigo"
                    value="<?= e($code) ?>"
                    placeholder="PN5K-000001"
                    required
                    autocomplete="off"
                >
                <?php if ($code !== ''): ?>
                    <small>El número fue colocado automáticamente.</small>
                <?php endif; ?>
            </label>

            <label>
                Últimos cuatro caracteres de identificación
                <input
                    name="ultimos4"
                    value="<?= e($last4) ?>"
                    maxlength="4"
                    minlength="4"
                    required
                    autocomplete="off"
                    inputmode="text"
                    placeholder="Ejemplo: 1234"
                >
                <small>Los guiones y espacios de la identificación no se toman en cuenta.</small>
            </label>

            <button class="btn" type="submit">Consultar estado</button>
        </form>

        <?php if ($result): ?>
            <div class="status-result">
                <span class="badge <?= e(badge_class($result['estado'])) ?>">
                    <?= e(payment_status_label($result['estado'])) ?>
                </span>

                <h2><?= e($result['primer_nombre'] . ' ' . $result['primer_apellido']) ?></h2>

                <dl class="summary">
                    <div>
                        <dt>Código</dt>
                        <dd><?= e($result['codigo']) ?></dd>
                    </div>
                    <div>
                        <dt>Evento</dt>
                        <dd><?= e($result['evento']) ?></dd>
                    </div>
                    <div>
                        <dt>Categoría</dt>
                        <dd><?= e($result['categoria']) ?></dd>
                    </div>
                    <div>
                        <dt>Fecha</dt>
                        <dd><?= e(format_event_date($result['fecha_evento'])) ?></dd>
                    </div>
                    <div>
                        <dt>Entrega de kits</dt>
                        <dd><?= e($result['entrega_kit_texto']) ?></dd>
                    </div>
                </dl>

                <?php if ($result['observaciones']): ?>
                    <div class="alert alert-warning">
                        <?= e($result['observaciones']) ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <p class="center">
            <a href="index.php">← Volver a la carrera</a>
        </p>
    </section>
</main>
</body>
</html>

