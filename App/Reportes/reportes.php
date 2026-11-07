<?php
require_once __DIR__.'/../backend/auth.php';
require_access(['Administrador'],false);
require_once __DIR__.'/../backend/reportes.php';
function esc($v) { return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8'); }
$definitions=reportDefinitions();
$kind=is_string($_GET['reporte']??null)?$_GET['reporte']:'ventas';
$start=is_string($_GET['desde']??null)?$_GET['desde']:date('Y-m-01');
$end=is_string($_GET['hasta']??null)?$_GET['hasta']:date('Y-m-d');
$error='';$rows=[];
try { $rows=loadReport(inventoryDb(),$kind,$start,$end); }
catch (DomainException $e) { http_response_code(400);$error=$e->getMessage(); }
catch (PDOException $e) { http_response_code(503);$error='No se pudo consultar el reporte. Comprueba la actualización de la base e inténtalo de nuevo.'; }
$definition=$definitions[$kind]??$definitions['ventas'];
if (($_GET['exportar']??'')==='csv' && $error==='') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="reporte-'.$kind.'_'.$start.'_'.$end.'.csv"');
    $output=fopen('php://output','w');fwrite($output,"\xEF\xBB\xBF");
    fputcsv($output,$definition['columnas'],';', '"', '');
    foreach ($rows as $row) fputcsv($output,array_map('reportCsvValue',$row),';','"','');
    fclose($output);exit;
}
$export='reportes.php?'.http_build_query(['desde'=>$start,'hasta'=>$end,'reporte'=>$kind,'exportar'=>'csv']);
?>
<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Reportes · Florería</title><link rel="stylesheet" href="../css/usuarios.css"><link rel="stylesheet" href="../css/catalogo.css?v=<?= filemtime(__DIR__.'/../css/catalogo.css') ?>"></head><body><main class="catalog-page">
<a class="back-link" href="../menu.php">Volver al menú</a>
<header class="catalog-header"><div><span class="eyebrow">FLORERÍA / ADMINISTRACIÓN</span><h1>Reportes</h1><p>Consulta la actividad de tu florería y descarga sus registros.</p></div></header>
<?php if ($error): ?><p class="notice notice-error" role="alert"><?= esc($error) ?></p><?php endif; ?>
<section class="user-card"><form class="search report-filter" method="get"><div>
<div><label for="desde">Desde</label><input id="desde" name="desde" type="date" value="<?= esc($start) ?>" required></div>
<div><label for="hasta">Hasta</label><input id="hasta" name="hasta" type="date" value="<?= esc($end) ?>" required></div>
<div><label for="reporte">Reporte</label><select id="reporte" name="reporte"><?php foreach ($definitions as $key=>$item): ?><option value="<?= $key ?>" <?= $kind===$key?'selected':'' ?>><?= esc($item['titulo']) ?></option><?php endforeach; ?></select></div>
<button>Consultar</button></div></form></section>
<?php if ($error===''): ?>
<section class="user-card catalog-list"><div class="list-heading"><div><h2><?= esc($definition['titulo']) ?></h2><p class="catalog-result-count"><?= count($rows) ?> <?= count($rows)===1?'fila':'filas' ?> · <?= esc($start) ?> a <?= esc($end) ?></p></div><a class="action" href="<?= esc($export) ?>">Descargar CSV</a></div>
<p class="report-note"><?= esc($definition['nota']) ?> Las unidades sin dato histórico se indican como “Sin unidad registrada”.</p>
<div class="table-wrap"><table><thead><tr><?php foreach ($definition['columnas'] as $column): ?><th scope="col"><?= esc($column) ?></th><?php endforeach; ?></tr></thead><tbody>
<?php foreach ($rows as $row): ?><tr><?php foreach ($row as $value): ?><td><?= esc($value) ?></td><?php endforeach; ?></tr><?php endforeach; ?>
<?php if (!$rows): ?><tr><td colspan="<?= count($definition['columnas']) ?>">No hay datos para este reporte y periodo.</td></tr><?php endif; ?>
</tbody></table></div></section>
<?php endif; ?>
</main></body></html>
