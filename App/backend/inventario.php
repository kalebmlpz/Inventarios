<?php
date_default_timezone_set('America/Mexico_City');
function inventoryDb() {
    require __DIR__ . '/config.php';
    return new PDO("mysql:host={$db['host']};dbname={$db['db']};charset=utf8mb4", $db['username'], $db['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
}
function inventoryLock($conn) {
    $conn->beginTransaction();
    $conn->query('SELECT id FROM inventario_lock WHERE id=1 FOR UPDATE')->fetch();
}
function csrfCheck($json = false) {
    if (!is_string($_POST['csrf'] ?? null) || empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $_POST['csrf'])) {
        http_response_code(403); exit($json ? json_encode(['message'=>'Solicitud no permitida. Recarga la página.']) : 'Solicitud no permitida. Recarga la página.');
    }
}
function operationToken() { return bin2hex(random_bytes(24)); }
function inventoryProduct($conn, $id) {
    $stmt = $conn->prepare('SELECT * FROM articulo WHERE id_articulo=?');
    $stmt->execute([$id]); $product = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$product) throw new DomainException('El producto ya no existe.');
    return $product;
}
function existingOperation($conn, $token, $actor) {
    $stmt = $conn->prepare('SELECT * FROM registros WHERE operacion_id=?');
    $stmt->execute([$token]); $record = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($record && (int)$record['usuario_id'] !== (int)$actor) throw new DomainException('Operación no permitida.');
    return $record;
}
function recordOperation($conn, $product, $quantity, $type, $actor, $date, $token = null, $mode = null) {
    $stmt = $conn->prepare('INSERT INTO registros(sku,cantidad,fecha,usuario_id,tipo,nombre_snapshot,operacion_id,modo) VALUES(?,?,?,?,?,?,?,?)');
    $stmt->execute([$product['sku'], $quantity, $date, $actor, $type, $product['nombre'], $token, $mode]);
    return $conn->lastInsertId();
}
function changeStock($conn, $product, $delta, $record) {
    $new = (int)$product['stock'] + $delta;
    if ($new < 0) throw new DomainException('Existencias insuficientes de '.$product['nombre'].'. Disponibles: '.$product['stock']);
    if ($new > 2147483647) throw new DomainException('La cantidad supera el límite permitido.');
    $stmt = $conn->prepare('UPDATE articulo SET stock=? WHERE id_articulo=?');
    $stmt->execute([$new, $product['id_articulo']]);
    $stmt = $conn->prepare('INSERT INTO movimientos(registro_id,sku,nombre,cantidad,stock_resultante) VALUES(?,?,?,?,?)');
    $stmt->execute([$record, $product['sku'], $product['nombre'], $delta, $new]);
}
function consumeRecipe($conn, $product, $quantity, $record) {
    $stmt = $conn->prepare('SELECT componente_id,cantidad FROM componentes WHERE arreglo_id=? ORDER BY componente_id');
    $stmt->execute([$product['id_articulo']]); $recipe = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (!$recipe) throw new DomainException('Este arreglo todavía no tiene componentes. Pide al Administrador que los configure.');
    foreach ($recipe as $item) {
        $material = inventoryProduct($conn, $item['componente_id']);
        if ($material['categoria'] === 'Arreglos') throw new DomainException('Un arreglo debe usar materiales individuales.');
        $required = (int)$item['cantidad'] * $quantity;
        if ($required > 2147483647) throw new DomainException('La cantidad de materiales es demasiado grande.');
        changeStock($conn, $material, -$required, $record);
    }
}
