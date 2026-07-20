<?php
require __DIR__ . '/src/bootstrap.php';
$code = $_SESSION['last_registration_code'] ?? null;
unset($_SESSION['last_registration_code']);
if (!$code) { redirect('index.php'); }
$stmt = $pdo->prepare('SELECT i.codigo, i.estado, c.nombre categoria, e.nombre evento, e.fecha_evento, e.entrega_kit_texto FROM inscripciones i JOIN categorias c ON c.id=i.categoria_id JOIN eventos e ON e.id=i.evento_id WHERE i.codigo=?');
$stmt->execute([$code]);
$row = $stmt->fetch();
?>
<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Inscripción recibida</title><link rel="stylesheet" href="assets/css/app.css"></head><body class="page-bg"><main class="container narrow"><section class="card success-card"><div class="success-icon">✓</div><span class="section-kicker">Solicitud recibida</span><h1>Tu pago está pendiente de validación</h1><p>El encargado revisará la captura enviada. El cupo quedará reservado únicamente después de confirmar el pago.</p><div class="code-box"><span>Código de inscripción</span><strong><?= e($row['codigo']) ?></strong></div><dl class="summary"><div><dt>Evento</dt><dd><?= e($row['evento']) ?></dd></div><div><dt>Fecha</dt><dd><?= e(format_event_date($row['fecha_evento'])) ?></dd></div><div><dt>Categoría</dt><dd><?= e($row['categoria']) ?></dd></div><div><dt>Entrega de kits</dt><dd><?= e($row['entrega_kit_texto']) ?></dd></div></dl><p class="muted">Guarda tu código. Lo necesitarás para consultar el estado de la inscripción.</p><div class="hero-actions center"><a class="btn" href="estado.php">Consultar estado</a><a class="btn btn-outline" href="index.php">Volver al inicio</a></div></section></main></body></html>
