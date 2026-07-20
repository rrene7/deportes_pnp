<?php
require __DIR__ . '/src/bootstrap.php';
$result = null;
$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $code = strtoupper(trim((string)($_POST['codigo'] ?? '')));
    $last4 = trim((string)($_POST['ultimos4'] ?? ''));
    $stmt = $pdo->prepare('SELECT i.codigo,i.estado,i.identificacion,i.primer_nombre,i.primer_apellido,c.nombre categoria,e.nombre evento,e.fecha_evento,e.entrega_kit_texto,p.estado pago_estado,p.observaciones FROM inscripciones i JOIN categorias c ON c.id=i.categoria_id JOIN eventos e ON e.id=i.evento_id JOIN pagos p ON p.inscripcion_id=i.id WHERE i.codigo=? LIMIT 1');
    $stmt->execute([$code]);
    $row = $stmt->fetch();
    $normalized = $row ? strtoupper(preg_replace('/[^A-Z0-9]/i', '', $row['identificacion'])) : '';
    $lookup = strtoupper(preg_replace('/[^A-Z0-9]/i', '', $last4));
    if (!$row || strlen($lookup) !== 4 || !str_ends_with($normalized, $lookup)) {
        $error = 'No encontramos una inscripción con esos datos.';
    } else { $result = $row; }
}
?>
<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Consultar inscripción</title><link rel="stylesheet" href="assets/css/app.css"></head><body class="page-bg"><main class="container narrow"><section class="card"><span class="section-kicker">Consulta pública</span><h1>Verificar mi inscripción</h1><p class="muted">Ingresa el código recibido y los últimos cuatro números de tu cédula o pasaporte.</p><?php if($error):?><div class="alert alert-danger"><?=e($error)?></div><?php endif;?>
<form method="post" class="form-grid one-column"><?=csrf_field()?><label>Código de inscripción<input name="codigo" placeholder="PN5K-000001" required></label><label>Últimos cuatro caracteres de identificación<input name="ultimos4" maxlength="4" required></label><button class="btn" type="submit">Consultar</button></form>
<?php if($result):?><div class="status-result"><span class="badge <?=e(badge_class($result['estado']))?>"><?=e(payment_status_label($result['estado']))?></span><h2><?=e($result['primer_nombre'].' '.$result['primer_apellido'])?></h2><dl class="summary"><div><dt>Código</dt><dd><?=e($result['codigo'])?></dd></div><div><dt>Categoría</dt><dd><?=e($result['categoria'])?></dd></div><div><dt>Fecha</dt><dd><?=e(format_event_date($result['fecha_evento']))?></dd></div><div><dt>Entrega de kits</dt><dd><?=e($result['entrega_kit_texto'])?></dd></div></dl><?php if($result['observaciones']):?><div class="alert alert-warning"><?=e($result['observaciones'])?></div><?php endif;?></div><?php endif;?>
<p class="center"><a href="index.php">← Volver a la carrera</a></p></section></main></body></html>
