<?php
require dirname(__DIR__) . '/src/bootstrap.php';
$user=require_admin();
$pageTitle='Panel principal';
$event=$pdo->query("SELECT * FROM eventos WHERE slug='carrera-5k-policia-2026' LIMIT 1")->fetch();
$stats=[];
foreach([
 'total'=>'SELECT COUNT(*) FROM inscripciones WHERE evento_id=?',
 'pendientes'=>"SELECT COUNT(*) FROM inscripciones WHERE evento_id=? AND estado='pago_pendiente'",
 'confirmados'=>"SELECT COUNT(*) FROM inscripciones WHERE evento_id=? AND estado IN ('pago_confirmado','kit_entregado')",
 'rechazados'=>"SELECT COUNT(*) FROM inscripciones WHERE evento_id=? AND estado='pago_rechazado'",
 'kits'=>"SELECT COUNT(*) FROM inscripciones WHERE evento_id=? AND estado='kit_entregado'",
] as $key=>$sql){$s=$pdo->prepare($sql);$s->execute([$event['id']]);$stats[$key]=(int)$s->fetchColumn();}
$cat=$pdo->prepare("SELECT c.nombre, COUNT(i.id) total FROM categorias c LEFT JOIN inscripciones i ON i.categoria_id=c.id AND i.estado IN ('pago_confirmado','kit_entregado') WHERE c.evento_id=? GROUP BY c.id ORDER BY c.edad_min");$cat->execute([$event['id']]);$catStats=$cat->fetchAll();
$recent=$pdo->prepare('SELECT i.id,i.codigo,i.primer_nombre,i.primer_apellido,i.estado,i.creado_en,c.nombre categoria FROM inscripciones i JOIN categorias c ON c.id=i.categoria_id WHERE i.evento_id=? ORDER BY i.id DESC LIMIT 8');$recent->execute([$event['id']]);
require __DIR__.'/_header.php';
?>
<div class="admin-heading"><div><span class="section-kicker">Resumen operativo</span><h1><?=e($event['nombre'])?></h1><p><?=e(format_event_date($event['fecha_evento']))?> · Hora <?= $event['hora_confirmada']?e(date('g:i a',strtotime($event['hora_salida']))):'por confirmar'?></p></div><a class="btn" href="inscripciones.php?estado=pago_pendiente">Revisar pagos</a></div>
<section class="stats-grid"><div class="stat-card"><span>Total de solicitudes</span><strong><?=$stats['total']?></strong></div><div class="stat-card warning"><span>Pagos pendientes</span><strong><?=$stats['pendientes']?></strong></div><div class="stat-card success"><span>Cupos confirmados</span><strong><?=$stats['confirmados']?></strong></div><div class="stat-card"><span>Kits entregados</span><strong><?=$stats['kits']?></strong></div></section>
<section class="admin-grid"><div class="card"><h2>Confirmados por categoría</h2><?php foreach($catStats as $row):?><div class="metric-row"><span><?=e($row['nombre'])?></span><strong><?=$row['total']?></strong></div><?php endforeach;?></div><div class="card"><h2>Configuración rápida</h2><dl class="summary"><div><dt>Yappy</dt><dd><?=e($event['yappy_numero'])?></dd></div><div><dt>Precio</dt><dd><?=e(format_money($event['precio']))?></dd></div><div><dt>Cupos</dt><dd><?=e($event['cupos']??'Sin límite definido')?></dd></div><div><dt>Inscripciones</dt><dd><?= $event['inscripciones_abiertas']?'Abiertas':'Cerradas'?></dd></div></dl><a href="evento.php">Editar evento →</a></div></section>
<section class="card table-card"><div class="table-header"><h2>Inscripciones recientes</h2><a href="inscripciones.php">Ver todas</a></div><div class="table-wrap"><table><thead><tr><th>Código</th><th>Participante</th><th>Categoría</th><th>Estado</th><th>Fecha</th></tr></thead><tbody><?php foreach($recent as $r):?><tr><td><a href="ver.php?id=<?=$r['id']?>"><?=e($r['codigo'])?></a></td><td><?=e($r['primer_nombre'].' '.$r['primer_apellido'])?></td><td><?=e($r['categoria'])?></td><td><span class="badge <?=e(badge_class($r['estado']))?>"><?=e(payment_status_label($r['estado']))?></span></td><td><?=e(date('d/m/Y H:i',strtotime($r['creado_en'])))?></td></tr><?php endforeach;?></tbody></table></div></section>
<?php require __DIR__.'/_footer.php'; ?>
