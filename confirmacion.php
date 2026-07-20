<?php
require __DIR__ . '/src/bootstrap.php';

$code = $_SESSION['last_registration_code'] ?? null;
if (!$code) {
    redirect('index.php');
}

$stmt = $pdo->prepare(
    'SELECT
        i.codigo,
        i.estado,
        i.primer_nombre,
        i.primer_apellido,
        c.nombre AS categoria,
        e.nombre AS evento,
        e.fecha_evento,
        e.entrega_kit_texto
     FROM inscripciones i
     JOIN categorias c ON c.id = i.categoria_id
     JOIN eventos e ON e.id = i.evento_id
     WHERE i.codigo = ?
     LIMIT 1'
);
$stmt->execute([$code]);
$row = $stmt->fetch();

if (!$row) {
    redirect('index.php');
}

$scriptDirectory = dirname($_SERVER['SCRIPT_NAME'] ?? '/');
$basePath = rtrim(str_replace('\\', '/', $scriptDirectory), '/');
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';

$stateRelativeUrl = 'estado.php?codigo=' . rawurlencode($row['codigo']);
$stateAbsoluteUrl = $scheme . '://' . $host . $basePath . '/' . $stateRelativeUrl;

$shareText = sprintf(
    "Preinscripción recibida para %s.\nCódigo de inscripción: %s\nEstado: pago pendiente de validación.\nConsulta el estado aquí: %s\nPara ingresar también se requieren los últimos cuatro caracteres de la identificación registrada.",
    $row['evento'],
    $row['codigo'],
    $stateAbsoluteUrl
);
$whatsappUrl = 'https://wa.me/?text=' . rawurlencode($shareText);
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Inscripción recibida</title>
    <link rel="stylesheet" href="assets/css/app.css">
    <link rel="stylesheet" href="assets/css/sports-theme.css">
    <style>
        .confirmation-actions {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 12px;
            margin-top: 22px;
        }
        .confirmation-actions .btn {
            width: 100%;
        }
        .copy-status {
            min-height: 24px;
            margin: 8px 0 0;
            font-weight: 700;
            color: var(--success, #18794e);
        }
        .consult-instructions {
            text-align: left;
            margin: 24px 0;
            padding: 18px;
            border-radius: 14px;
            background: #f5f9fd;
            border: 1px solid #dce6ef;
        }
        .consult-instructions h2 {
            margin-top: 0;
            font-size: 1.05rem;
        }
        .consult-instructions ol {
            margin-bottom: 0;
            padding-left: 22px;
        }
        @media (max-width: 620px) {
            .confirmation-actions {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body class="page-bg">
<main class="container narrow">
    <section class="card success-card">
        <div class="success-icon">✓</div>
        <span class="section-kicker">Preinscripción recibida</span>

        <h1>Tu comprobante fue enviado</h1>

        <p>
            El pago se encuentra <strong>pendiente de validación</strong>.
            El cupo quedará reservado cuando el encargado confirme el pago.
        </p>

        <div class="code-box">
            <span>Tu número de inscripción</span>
            <strong id="registrationCode"><?= e($row['codigo']) ?></strong>
        </div>

        <button class="btn btn-outline" type="button" id="copyCodeButton">
            Copiar número de inscripción
        </button>
        <p class="copy-status" id="copyStatus" aria-live="polite"></p>

        <dl class="summary">
            <div>
                <dt>Evento</dt>
                <dd><?= e($row['evento']) ?></dd>
            </div>
            <div>
                <dt>Fecha</dt>
                <dd><?= e(format_event_date($row['fecha_evento'])) ?></dd>
            </div>
            <div>
                <dt>Categoría</dt>
                <dd><?= e($row['categoria']) ?></dd>
            </div>
            <div>
                <dt>Entrega de kits</dt>
                <dd><?= e($row['entrega_kit_texto']) ?></dd>
            </div>
            <div>
                <dt>Estado actual</dt>
                <dd>Pago pendiente de validación</dd>
            </div>
        </dl>

        <div class="consult-instructions">
            <h2>¿Cómo consultar después?</h2>
            <ol>
                <li>Guarda el número de inscripción mostrado arriba.</li>
                <li>Abre la opción <strong>Consultar mi inscripción</strong>.</li>
                <li>Escribe los últimos cuatro caracteres de la cédula o pasaporte registrado.</li>
            </ol>
        </div>

        <div class="confirmation-actions">
            <a class="btn" href="<?= e($stateRelativeUrl) ?>">
                Consultar mi inscripción
            </a>

            <a
                class="btn btn-outline"
                href="<?= e($whatsappUrl) ?>"
                target="_blank"
                rel="noopener noreferrer"
            >
                Compartir por WhatsApp
            </a>

            <a class="btn btn-outline" href="index.php">
                Volver al inicio
            </a>
        </div>
    </section>
</main>

<script>
(() => {
    const button = document.getElementById('copyCodeButton');
    const codeElement = document.getElementById('registrationCode');
    const status = document.getElementById('copyStatus');

    async function copyCode() {
        const code = codeElement?.textContent?.trim() || '';

        if (!code) {
            status.textContent = 'No fue posible encontrar el número de inscripción.';
            return;
        }

        try {
            if (navigator.clipboard && window.isSecureContext) {
                await navigator.clipboard.writeText(code);
            } else {
                const temporary = document.createElement('textarea');
                temporary.value = code;
                temporary.setAttribute('readonly', '');
                temporary.style.position = 'fixed';
                temporary.style.opacity = '0';
                document.body.appendChild(temporary);
                temporary.select();

                const copied = document.execCommand('copy');
                temporary.remove();

                if (!copied) {
                    throw new Error('No se pudo copiar');
                }
            }

            status.textContent = 'Número copiado: ' + code;
            button.textContent = 'Número copiado';
        } catch (error) {
            status.textContent = 'Copia manualmente este número: ' + code;
        }
    }

    button?.addEventListener('click', copyCode);
})();
</script>
</body>
</html>

