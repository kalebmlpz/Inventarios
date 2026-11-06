<?php
require_once __DIR__.'/../backend/auth.php';
$user=require_access(['Administrador','Cajero'],false);
if (empty($_SESSION['csrf'])) $_SESSION['csrf']=bin2hex(random_bytes(24));
?>
<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Ventas · Florería</title><link rel="stylesheet" href="../css/usuarios.css"><link rel="stylesheet" href="../css/catalogo.css?v=<?= filemtime(__DIR__.'/../css/catalogo.css') ?>"></head><body><main class="catalog-page">
<a class="back-link" href="../menu.php">Volver al menú</a><header class="catalog-header"><div><span class="eyebrow">FLORERÍA / VENTAS</span><h1>Registrar venta</h1><p>Vende productos disponibles o elabora un arreglo por encargo.</p></div></header>
<div class="sales-grid"><section class="user-card"><form id="formulario-articulos" class="product-form"><h2>Agregar producto</h2><div class="field"><label for="sku">Código numérico</label><input id="sku" type="number" min="1" required></div><div class="field"><label for="cantidad">Cantidad</label><input id="cantidad" type="number" min="1" step="1" value="1" required></div><button>Buscar producto</button></form><div id="seleccion-producto" class="product-form" hidden><p id="nombre-producto"></p><label for="modo">Modalidad de venta</label><select id="modo"></select><p class="field-note">Disponible: descuenta el producto terminado. Por encargo: consume sus componentes al confirmar la venta y elaborar el arreglo.</p><button type="button" id="agregar-producto">Agregar al pedido</button></div></section>
<section class="user-card"><div class="product-form"><h2>Pedido</h2><div id="articulos"></div><p id="estado-venta" role="status"></p><button id="finalizar" type="button" disabled>Finalizar pedido</button></div></section></div>
<input type="hidden" id="csrf" value="<?= htmlspecialchars($_SESSION['csrf'],ENT_QUOTES,'UTF-8') ?>"><input type="hidden" id="usuario" value="<?= (int)$user['id'] ?>">
</main><script src="js/venta.js?v=<?= filemtime(__DIR__.'/js/venta.js') ?>" defer></script></body></html>
