<?php
require_once __DIR__.'/../backend/auth.php';
$user=require_access(['Administrador'],false);
require_once __DIR__.'/../backend/correcciones_ventas.php';
function esc($value) { return htmlspecialchars((string)$value,ENT_QUOTES,'UTF-8'); }
$id=filter_var($_GET['id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
if (!$id) { http_response_code(400); exit('Selecciona una venta desde Registros.'); }
$conn=inventoryDb();
try { $sale=saleForCorrection($conn,$id); } catch (DomainException $e) { http_response_code(404); exit(esc($e->getMessage())); }
if (empty($_SESSION['csrf'])) $_SESSION['csrf']=operationToken();
$values=['tipo'=>'Cancelación','cantidad'=>'1','tratamiento'=>'sin_stock','motivo'=>'','operacion_id'=>operationToken()];
$error='';
if ($_SERVER['REQUEST_METHOD']==='POST') {
    csrfCheck();
    foreach ($values as $key=>$value) $values[$key]=is_string($_POST[$key]??null)?$_POST[$key]:'';
    try {
        correctSale($conn,$user['id'],$id,$_POST);
        $_SESSION['sale_correction_message']='Corrección registrada. La venta original y su historial se conservan.';
        header('Location: registros.php?fecha='.rawurlencode($sale['fecha']),true,303); exit;
    } catch (DomainException $e) { http_response_code(422); $error=$e->getMessage(); }
      catch (PDOException $e) { http_response_code(500); $error='No se pudo registrar. Comprueba la actualización de la base e inténtalo de nuevo.'; }
    $sale=saleForCorrection($conn,$id);
}
?>
<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Cancelar o devolver · Florería</title><link rel="stylesheet" href="../css/usuarios.css?v=<?= filemtime(__DIR__ . '/../css/usuarios.css') ?>"><link rel="stylesheet" href="../css/catalogo.css?v=<?= filemtime(__DIR__.'/../css/catalogo.css') ?>"></head><body><main class="catalog-page movement-page">
<a class="back-link" href="registros.php?fecha=<?= esc($sale['fecha']) ?>">Volver a Registros</a>
<header class="catalog-header"><div><span class="eyebrow">FLORERÍA / CORRECCIONES</span><h1>Cancelar o devolver</h1><p>Venta #<?= (int)$sale['id'] ?> · <?= esc($sale['nombre_snapshot'] ?? 'Código '.$sale['sku']) ?> · <?= esc($sale['fecha']) ?></p></div></header>
<p class="notice">Vendidas: <?= (int)$sale['cantidad'] ?> · Corregidas: <?= (int)$sale['cantidad_corregida'] ?> · Pendientes: <?= (int)$sale['pendientes'] ?></p>
<?php if ($sale['precio_unitario']!==null): ?><p class="field-note">Precio original por unidad: <?= esc(moneyAmount($sale['precio_unitario'])) ?> <?= esc($sale['moneda']) ?>. La corrección conserva ese precio.</p><?php endif; ?>
<?php if ($error): ?><p class="notice notice-error" role="alert"><?= esc($error) ?></p><?php endif; ?>
<section class="user-card"><form method="post" class="product-form"><input type="hidden" name="csrf" value="<?= esc($_SESSION['csrf']) ?>"><input type="hidden" name="operacion_id" value="<?= esc($values['operacion_id']) ?>">
<div class="form-grid"><div class="field"><label for="tipo">Tipo de operación</label><select name="tipo" id="tipo"><?php foreach (['Cancelación','Devolución'] as $option): ?><option <?= $values['tipo']===$option?'selected':'' ?>><?= esc($option) ?></option><?php endforeach; ?></select></div><div class="field"><label for="cantidad">Unidades a corregir</label><input name="cantidad" id="cantidad" type="number" min="1" max="<?= (int)$sale['pendientes'] ?>" value="<?= esc($values['cantidad']) ?>" required></div></div>
<div class="field"><label for="tratamiento">¿Qué pasa con las existencias?</label><select name="tratamiento" id="tratamiento"><option value="sin_stock" <?= $values['tratamiento']==='sin_stock'?'selected':'' ?>>Sin reintegro de mercancía</option><option value="producto" <?= $values['tratamiento']==='producto'?'selected':'' ?>>Reintegrar producto recibido en buen estado</option><option value="captura" <?= $values['tratamiento']==='captura'?'selected':'' ?>>Corregir captura por error: revertir consumo original</option></select></div>
<p class="field-note">Sin reintegro conserva el stock actual. Reintegrar producto añade la unidad recibida, nunca sus componentes. Revertir captura solo corresponde a productos o materiales que no se entregaron ni utilizaron; en arreglos por encargo revierte los componentes consumidos.</p>
<?php if (!$sale['articulo_id']): ?><p class="field-note">Esta venta es anterior a esta actualización. No se reintegrará mercancía automáticamente sin una identificación segura.</p><?php endif; ?>
<div class="field"><label for="motivo">Motivo</label><textarea name="motivo" id="motivo" maxlength="400" rows="3" required placeholder="Explica la cancelación o devolución…"><?= esc($values['motivo']) ?></textarea></div>
<label class="confirmation-label"><input type="checkbox" name="confirmar" value="1" required> Confirmo la cantidad y revisé el estado de la mercancía. Si revierto una captura, los productos o materiales no se entregaron ni utilizaron.</label>
<div class="form-actions"><a class="back-link" href="registros.php?fecha=<?= esc($sale['fecha']) ?>">Volver sin guardar</a><button <?= $sale['pendientes']<=0?'disabled':'' ?>>Registrar corrección</button></div>
</form></section></main><script src="js/devolucion.js" defer></script></body></html>
