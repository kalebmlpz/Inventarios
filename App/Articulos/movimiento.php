<?php
require_once __DIR__ . '/../backend/auth.php';
$user = require_access(['Administrador'], false);
require_once __DIR__ . '/../backend/movimientos_stock.php';
function esc($value) { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
$type = $_GET['tipo'] ?? '';
if (!in_array($type, ['entrada', 'merma'], true)) { http_response_code(400); exit('Elige Registrar entrada o Registrar merma desde el catálogo.'); }
if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = operationToken();
$conn = inventoryDb();
$title = $type === 'entrada' ? 'Registrar entrada' : 'Registrar merma';
$error = '';
$values = ['producto_id' => '', 'cantidad' => '', 'motivo' => '', 'detalle' => '', 'operacion_id' => operationToken()];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfCheck();
    foreach ($values as $key => $value) $values[$key] = is_string($_POST[$key] ?? null) ? $_POST[$key] : '';
    try {
        registerStockMovement($conn, $user['id'], $type, $_POST);
        $_SESSION['stock_movement_message'] = $type === 'entrada' ? 'Entrada registrada. Las existencias se actualizaron.' : 'Merma registrada. Las existencias se actualizaron.';
        header('Location: consulta.php', true, 303); exit;
    } catch (DomainException $e) { http_response_code(422); $error = $e->getMessage(); }
      catch (PDOException $e) { http_response_code(500); $error = 'No se pudo registrar. Comprueba que la base de datos esté actualizada e inténtalo de nuevo.'; }
}
$stmt = $conn->query("SELECT id_articulo, sku, nombre, unidad, stock FROM articulo" . ($type === 'entrada' ? " WHERE categoria <> 'Arreglos'" : '') . ' ORDER BY nombre');
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?= esc($title) ?> · Florería</title><link rel="stylesheet" href="../css/usuarios.css?v=<?= filemtime(__DIR__ . '/../css/usuarios.css') ?>"><link rel="stylesheet" href="../css/catalogo.css?v=<?= filemtime(__DIR__.'/../css/catalogo.css') ?>"></head>
<body><main class="catalog-page movement-page inventory-movement">
<a class="back-link" href="consulta.php">Volver al catálogo</a>
<header class="catalog-header"><div><span class="eyebrow">FLORERÍA / EXISTENCIAS</span><h1><?= esc($title) ?></h1><p><?= $type === 'entrada' ? 'Recibe mercancía y suma unidades a tu inventario.' : 'Registra las pérdidas para mantener tus existencias al día.' ?></p></div></header>
<?php if ($error): ?><p class="notice notice-error" role="alert"><?= esc($error) ?></p><?php endif; ?>
<section class="user-card inventory-form-card"><form method="post" class="product-form">
<input type="hidden" name="csrf" value="<?= esc($_SESSION['csrf']) ?>"><input type="hidden" name="operacion_id" value="<?= esc($values['operacion_id']) ?>">
<div class="field"><label for="producto_id">Producto</label><select name="producto_id" id="producto_id" required><option value="">Selecciona un producto</option><?php foreach ($products as $product): ?><option value="<?= (int)$product['id_articulo'] ?>" <?= (string)$product['id_articulo'] === $values['producto_id'] ? 'selected' : '' ?>><?= esc($product['nombre'].' · '.$product['sku'].' · disponibles: '.$product['stock'].' '.stockUnit($product['stock'], $product['unidad'])) ?></option><?php endforeach; ?></select></div>
<div class="form-grid"><div class="field"><label for="cantidad">Cantidad <?= $type === 'entrada' ? 'recibida' : 'perdida' ?></label><input name="cantidad" id="cantidad" type="number" min="1" max="2147483647" step="1" value="<?= esc($values['cantidad']) ?>" required></div>
<div class="field"><label for="motivo">Motivo</label><select name="motivo" id="motivo" required><option value="">Selecciona un motivo</option><?php foreach (movementReasons($type) as $reason): ?><option <?= $reason === $values['motivo'] ? 'selected' : '' ?>><?= esc($reason) ?></option><?php endforeach; ?></select></div></div>
<div class="field"><label for="detalle">Detalle <span class="optional">/ obligatorio si eliges Otro</span></label><textarea name="detalle" id="detalle" rows="3" maxlength="400" placeholder="Agrega una referencia o explica lo sucedido…"><?= esc($values['detalle']) ?></textarea></div>
<p class="field-note"><?= $type === 'entrada' ? 'Los arreglos terminados se registran desde Componentes / Preparar.' : 'Una merma de un arreglo terminado no devuelve sus materiales al inventario.' ?></p>
<div class="form-actions"><a class="back-link" href="consulta.php">Cancelar</a><button <?= !$products ? 'disabled' : '' ?>><?= esc($title) ?></button></div>
<?php if (!$products): ?><p role="status">No hay productos disponibles para esta operación. Registra primero un material en el catálogo.</p><?php endif; ?>
</form></section></main></body></html>
