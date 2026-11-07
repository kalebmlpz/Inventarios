<?php
require_once __DIR__ . '/../backend/auth.php';
$currentUser = require_access(['Administrador', 'Cajero'], false);
$admin = $currentUser['rol'] === 'Administrador';
require_once __DIR__ . '/../backend/inventario.php';
$conn = inventoryDb();
if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
function esc($value) { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
$categorias = ['Flores', 'Arreglos', 'Bases', 'Accesorios', 'Sin clasificar'];
$unidades = ['Pieza', 'Tallo', 'Ramo', 'Paquete'];
$modalidades = ['Individual', 'Preparado', 'Por encargo', 'Ambas'];
$message = '';
$editing = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$admin || !is_string($_POST['csrf'] ?? null) || !hash_equals($_SESSION['csrf'], $_POST['csrf'])) {
        http_response_code(403); exit('Solicitud no permitida');
    }
    $values = [];
    foreach (['sku', 'nombre', 'categoria', 'unidad', 'stock', 'stock_minimo', 'precio', 'observaciones', 'modalidad'] as $key) {
        $values[$key] = is_string($_POST[$key] ?? null) ? trim($_POST[$key]) : '';
    }
    $valid = ctype_digit($values['sku']) && (int)$values['sku'] > 0 && (int)$values['sku'] <= 2147483647
        && $values['nombre'] !== '' && strlen($values['nombre']) <= 100
        && in_array($values['categoria'], $categorias, true) && in_array($values['unidad'], $unidades, true)
        && in_array($values['modalidad'], $modalidades, true) && strlen($values['observaciones']) <= 500
        && ctype_digit($values['stock']) && (int)$values['stock'] <= 2147483647
        && ctype_digit($values['stock_minimo']) && (int)$values['stock_minimo'] <= 2147483647
        && preg_match('/^\d{1,8}(\.\d{1,2})?$/', $values['precio']);
    if ($values['categoria'] !== 'Arreglos') $values['modalidad'] = 'Individual';
    if ($values['categoria'] === 'Arreglos' && $values['modalidad'] === 'Individual') $valid = false;
    if (!$valid || ($values['modalidad'] === 'Por encargo' && (int)$values['stock'] !== 0)) {
        $message = 'Revisa los datos. Las existencias son unidades enteras; un arreglo solo por encargo debe tener existencias cero.';
    } else {
        try {
            inventoryLock($conn);
            $id = filter_var($_POST['id'] ?? 0, FILTER_VALIDATE_INT);
            if ($id === false || $id < 0) throw new DomainException('Producto inválido.');
            $previous = $id ? inventoryProduct($conn, $id) : null;
            if ($previous && (int)$values['stock'] !== (int)$previous['stock']) throw new DomainException('Las existencias cambiaron o intentaste editarlas. Recarga la página y usa Registrar entrada o Registrar merma.');
            if (!$previous && $values['categoria'] === 'Arreglos' && (int)$values['stock'] !== 0) throw new DomainException('Usa Preparar arreglos para aumentar sus unidades disponibles.');
            $ref = $conn->prepare('SELECT COUNT(*) FROM componentes WHERE componente_id=?');
            $ref->execute([$id]);
            if ($values['categoria'] === 'Arreglos' && $ref->fetchColumn() > 0) throw new DomainException('Este material se usa en un arreglo; retíralo de sus componentes antes de cambiar su categoría.');
            $stmt = $conn->prepare('SELECT id_articulo FROM articulo WHERE sku = ? AND id_articulo <> ?');
            $stmt->execute([$values['sku'], $id]);
            if ($stmt->fetch()) {
            throw new DomainException('El código ya pertenece a otro producto.');
            } else {
                if ($id) {
                    $stmt = $conn->prepare('UPDATE articulo SET sku=:sku,nombre=:nombre,categoria=:categoria,unidad=:unidad,stock=:stock,stock_minimo=:stock_minimo,precio=:precio,observaciones=:observaciones,modalidad=:modalidad WHERE id_articulo=:id');
                    $stmt->execute($values + ['id' => $id]);
                } else {
                    $stmt = $conn->prepare("INSERT INTO articulo (sku,nombre,categoria,unidad,stock,stock_minimo,precio,observaciones,modalidad,marca,modelo,departamento,clase,familia,fecha_alta,cantidad,descontinuado,fecha_baja) VALUES (:sku,:nombre,:categoria,:unidad,:stock,:stock_minimo,:precio,:observaciones,:modalidad,'','',0,0,0,CURDATE(),0,0,'1900-01-01')");
                    $stmt->execute($values);
                    $id = $conn->lastInsertId();
                }
                $delta = (int)$values['stock'] - (int)($previous['stock'] ?? 0);
                if ($delta !== 0) {
                    $product = inventoryProduct($conn, $id);
                    $record = recordOperation($conn, $product, $delta, $previous ? 'Ajuste' : 'Entrada inicial', $currentUser['id'], date('Y-m-d'));
                    $product['stock'] = (int)($previous['stock'] ?? 0);
                    changeStock($conn, $product, $delta, $record);
                }
                $conn->commit();
                header('Location: consulta.php?guardado=1'); exit;

            }
        } catch (DomainException $e) { if ($conn->inTransaction()) $conn->rollBack(); $message = $e->getMessage(); }
          catch (PDOException $e) { if ($conn->inTransaction()) $conn->rollBack(); $message = 'No se pudo guardar el producto. Comprueba la actualización de la base.'; }
    }
    $editing = $values + ['id_articulo' => $_POST['id'] ?? 0];
}
if ($admin && isset($_GET['editar']) && !$editing) {
    $stmt = $conn->prepare('SELECT * FROM articulo WHERE id_articulo = ?');
    $stmt->execute([$_GET['editar']]); $editing = $stmt->fetch(PDO::FETCH_ASSOC);
}
$q = is_string($_GET['q'] ?? null) ? trim($_GET['q']) : '';
$categoryFilter = is_string($_GET['categoria'] ?? null) && in_array($_GET['categoria'], $categorias, true) ? $_GET['categoria'] : '';
$stockFilter = is_string($_GET['estado'] ?? null) && in_array($_GET['estado'], ['bajo', 'agotado'], true) ? $_GET['estado'] : '';
$query = 'SELECT * FROM articulo WHERE (nombre LIKE ? OR CAST(sku AS CHAR) LIKE ?)';
$params = ['%'.$q.'%', '%'.$q.'%'];
if ($categoryFilter !== '') { $query .= ' AND categoria=?'; $params[] = $categoryFilter; }
// Los arreglos solo por encargo consumen materiales; no mantienen unidades listas.
if ($stockFilter !== '') $query .= " AND NOT (categoria='Arreglos' AND modalidad='Por encargo')";
if ($stockFilter === 'bajo') $query .= ' AND stock<=stock_minimo';
if ($stockFilter === 'agotado') $query .= ' AND stock=0';
$query .= ' ORDER BY nombre';
$stmt = $conn->prepare($query);
$stmt->execute($params); $productos = $stmt->fetchAll(PDO::FETCH_ASSOC);
$showForm = $admin && ($editing || basename($_SERVER['SCRIPT_NAME']) === 'alta.php');
?>
<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Productos y existencias · Florería</title><link rel="stylesheet" href="../css/usuarios.css?v=<?= filemtime(__DIR__ . '/../css/usuarios.css') ?>"><link rel="stylesheet" href="../css/catalogo.css?v=<?= filemtime(__DIR__ . '/../css/catalogo.css') ?>"></head>
<body><main class="catalog-page">
<a class="back-link" href="<?= $showForm ? 'consulta.php' : '../menu.php' ?>"><?= $showForm ? 'Volver al catálogo' : 'Volver al menú' ?></a>
<header class="catalog-header"><div><span class="eyebrow">FLORERÍA / INVENTARIO</span><h1>Productos y existencias<span class="title-dot">.</span></h1><p>Todo lo que necesitas para dar vida a cada arreglo.</p></div><span class="header-note">Flores · Arreglos · Bases · Accesorios</span></header>
<?php if (isset($_GET['guardado'])): ?><p class="notice notice-success" role="status">Producto guardado correctamente.</p><?php endif; ?>
<?php if (isset($_GET['eliminado'])): ?><p class="notice notice-success" role="status">Producto eliminado del catálogo. Su historial de ventas se conserva.</p><?php endif; ?>
<?php if (isset($_SESSION['stock_movement_message'])): ?><p class="notice notice-success" role="status"><?= esc($_SESSION['stock_movement_message']) ?></p><?php unset($_SESSION['stock_movement_message']); endif; ?>
<?php if ($message): ?><p class="notice notice-error" role="alert"><?= esc($message) ?></p><?php endif; ?>
<?php if ($showForm): ?>
<section class="product-editor">
<aside class="editor-aside">
<span class="eyebrow">TU CATÁLOGO, EN ORDEN</span>
<svg class="botanical" viewBox="0 0 220 245" fill="none" aria-hidden="true">
<g stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
<path d="M112 230C114 185 112 142 110 104M112 204C76 192 58 172 54 143C86 148 109 168 112 204ZM113 176C146 163 161 143 164 116C134 121 115 145 113 176Z"/>
<path d="M110 102C77 99 65 78 79 65C63 41 83 24 103 40C114 12 143 24 137 49C167 49 172 77 148 88C158 114 130 128 110 102Z"/>
<path d="M110 102C100 88 93 78 99 63C113 64 123 77 119 91M119 91C133 81 139 69 137 49M79 65C92 65 100 72 105 82"/>
<circle cx="113" cy="86" r="8"/>
<path d="M91 233H138M100 240H130M67 211L78 204M154 199L163 205"/>
</g></svg>
<h2><?= $editing ? 'Un detalle hace la diferencia.' : 'Un nuevo detalle para tu florería.' ?></h2>
<p>Organiza cada flor y cada material desde su primera entrada al catálogo.</p>

</aside>
<form method="post" class="product-form">
<div class="form-heading"><h2><?= $editing ? 'Editar producto' : 'Nuevo producto' ?></h2></div>
<input type="hidden" name="csrf" value="<?= esc($_SESSION['csrf']) ?>"><input type="hidden" name="id" value="<?= esc($editing['id_articulo'] ?? 0) ?>">
<?php
function catalogInput($key, $label, $type, $min, $editing, $placeholder = '') {
    $value = $editing[$key] ?? (in_array($key, ['stock','stock_minimo','precio']) ? 0 : '');
?>
<div class="field"><label for="<?= $key ?>"><?= $label ?></label><input id="<?= $key ?>" name="<?= $key ?>" type="<?= $type ?>" <?= $min !== null ? 'min="'.$min.'"' : 'maxlength="100"' ?> step="<?= $key === 'precio' ? '0.01' : '1' ?>" value="<?= esc($value) ?>" placeholder="<?= esc($placeholder) ?>" required></div>
<?php }
function catalogSelect($key, $label, $options, $editing) { ?>
<div class="field"><label for="<?= $key ?>"><?= $label ?></label><select id="<?= $key ?>" name="<?= $key ?>"><?php foreach ($options as $option): ?><option <?= ($editing[$key] ?? '') === $option ? 'selected' : '' ?>><?= esc($option) ?></option><?php endforeach; ?></select></div>
<?php } ?>
<fieldset id="identidad"><legend>Datos del producto</legend><div class="form-grid">
<?php catalogInput('nombre','Nombre del producto','text',null,$editing,'Ej. Rosa roja Freedom'); catalogInput('sku','Código numérico','number',1,$editing,'Ej. 1001'); catalogSelect('categoria','Categoría',$categorias,$editing); catalogSelect('unidad','Unidad de medida',$unidades,$editing); ?>
</div></fieldset>
<fieldset id="existencias"><legend>Existencias y precio</legend><div class="form-grid stock-grid">
<?php catalogInput('stock','Disponibles','number',0,$editing); catalogInput('stock_minimo','Stock mínimo','number',0,$editing); catalogInput('precio','Precio por unidad','number',0,$editing); ?>
</div><p class="field-note">Las cantidades iniciales se indican al crear el material. Después, usa Registrar entrada o Registrar merma; para arreglos, Componentes / Preparar.</p></fieldset>
<fieldset id="detalles"><legend>Información adicional</legend>
<div id="arreglo-options">
<?php catalogSelect('modalidad','Modalidad del arreglo',$modalidades,$editing); ?>
<p class="field-note">Elige si estará listo para vender o se preparará por encargo.</p>
</div>
<div class="field"><label for="observaciones">Observaciones <span class="optional">/ opcional</span></label><textarea id="observaciones" name="observaciones" maxlength="500" rows="2" placeholder="Color, tamaño, cuidados o algún detalle especial…"><?= esc($editing['observaciones'] ?? '') ?></textarea></div>
</fieldset>
<div class="form-actions"><a class="back-link" href="consulta.php">Cancelar</a><button>Guardar producto</button></div>
</form></section>
<script src="./js/catalogo.js?v=<?= filemtime(__DIR__ . '/js/catalogo.js') ?>" defer></script>
<?php endif; ?>
<?php if (!$showForm): ?>
<section class="user-card catalog-list"><div class="list-heading"><div><h2>Catálogo</h2></div><?php if ($admin): ?><div class="catalog-actions"><a class="action" href="alta.php">Nuevo producto</a><a class="action" href="movimiento.php?tipo=entrada">Registrar entrada</a><a class="action" href="movimiento.php?tipo=merma">Registrar merma</a></div><?php endif; ?></div><form class="search catalog-filter" method="get"><div class="filter-fields">
<div><label for="q">Buscar producto</label><input id="q" name="q" value="<?= esc($q) ?>" placeholder="Nombre o código"></div>
<div><label for="categoria-filtro">Categoría</label><select id="categoria-filtro" name="categoria"><option value="">Todas</option><?php foreach ($categorias as $category): ?><option <?= $categoryFilter === $category ? 'selected' : '' ?>><?= esc($category) ?></option><?php endforeach; ?></select></div>
<div><label for="estado">Existencias</label><select id="estado" name="estado"><option value="">Todas</option><option value="bajo" <?= $stockFilter === 'bajo' ? 'selected' : '' ?>>Stock bajo</option><option value="agotado" <?= $stockFilter === 'agotado' ? 'selected' : '' ?>>Agotados</option></select></div>
<button>Buscar</button></div>
<div class="filter-description"><p class="field-note">Stock bajo: unidades disponibles iguales o menores al mínimo. No incluye arreglos solo por encargo.</p><?php if ($q !== '' || $categoryFilter !== '' || $stockFilter !== ''): ?><a class="edit-link" href="consulta.php">Limpiar filtros</a><?php endif; ?></div>
<p class="catalog-result-count"><?= count($productos) ?> <?= count($productos) === 1 ? 'producto encontrado' : 'productos encontrados' ?></p></form>
<div class="table-wrap"><table><thead><tr><th>Código</th><th>Producto</th><th>Categoría</th><th>Existencias</th><th>Mínimo</th><th>Precio</th><th>Modalidad</th><?php if ($admin): ?><th>Acciones</th><?php endif; ?></tr></thead><tbody>
<?php foreach ($productos as $producto): ?><tr><td><?= esc($producto['sku']) ?></td><td><strong><?= esc($producto['nombre']) ?></strong><?php if ($producto['observaciones']): ?><small><?= esc($producto['observaciones']) ?></small><?php endif; ?></td><td><?= esc($producto['categoria']) ?></td><td><?php $madeToOrder = $producto['categoria'] === 'Arreglos' && $producto['modalidad'] === 'Por encargo'; ?>
<?php if ($madeToOrder): ?><span class="stock-label">Por encargo</span><?php else: ?>
<span class="<?= $producto['stock'] <= $producto['stock_minimo'] ? 'low-stock' : '' ?>"><?= esc($producto['stock']) ?> <?= esc(stockUnit($producto['stock'], $producto['unidad'])) ?></span>
<?php if ((int)$producto['stock'] === 0): ?><small class="stock-badge stock-empty"><?= $producto['categoria'] === 'Arreglos' ? 'Sin arreglos listos' : 'Agotado' ?></small><?php elseif ($producto['stock'] <= $producto['stock_minimo']): ?><small class="stock-badge">Stock bajo</small><?php endif; ?>
<?php endif; ?></td><td><?= esc($producto['stock_minimo']) ?></td><td><?= number_format((float)$producto['precio'], 2) ?></td><td><?= esc($producto['modalidad']) ?></td><?php if ($admin): ?><td><div class="row-actions"><a class="edit-link" href="consulta.php?editar=<?= (int)$producto['id_articulo'] ?>">Editar</a><?php if ($producto['categoria'] === 'Arreglos'): ?><a class="edit-link" href="componentes.php?id=<?= (int)$producto['id_articulo'] ?>">Componentes / Preparar</a><?php endif; ?><form method="post" action="../backend/eliminar_producto.php" onsubmit="return confirm('¿Eliminar definitivamente este producto y sus existencias del catálogo? Esta acción no se puede deshacer. El historial de ventas se conserva.')"><input type="hidden" name="csrf" value="<?= esc($_SESSION['csrf']) ?>"><input type="hidden" name="id" value="<?= (int)$producto['id_articulo'] ?>"><button class="delete-product" aria-label="Eliminar <?= esc($producto['nombre']) ?>">Eliminar</button></form></div></td><?php endif; ?></tr><?php endforeach; ?>
<?php if (!$productos): ?><tr><td colspan="8">No se encontraron productos con estos filtros.</td></tr><?php endif; ?>
</tbody></table></div></section>
<?php endif; ?>
</main></body></html>
