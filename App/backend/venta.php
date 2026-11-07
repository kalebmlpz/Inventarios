<?php
require_once __DIR__ . '/auth.php';
$user = require_access();
require_once __DIR__ . '/inventario.php';
header('Content-Type: application/json; charset=utf-8');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); header('Allow: POST'); exit(json_encode(['message'=>'Método no permitido'])); }
csrfCheck(true);
$sku = $_POST['sku'] ?? null;
$qty = filter_var($_POST['cantidad'] ?? null, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1,'max_range'=>2147483647]]);
$date = $_POST['fecha'] ?? '';
$parsed = is_string($date) ? DateTimeImmutable::createFromFormat('!Y-m-d',$date) : false;
$token = $_POST['operacion_id'] ?? '';
$mode = $_POST['modo'] ?? 'Preparado';
if (!is_string($sku) || !ctype_digit($sku) || !$qty || !$parsed || $parsed->format('Y-m-d') !== $date || !is_string($token) || !preg_match('/^[a-zA-Z0-9-]{16,64}$/',$token) || !in_array($mode,['Preparado','Por encargo'],true)) {
    http_response_code(400); exit(json_encode(['message'=>'Revisa el producto, la cantidad, la modalidad y la fecha.']));
}
try {
    $conn=inventoryDb(); inventoryLock($conn);
    $existing=existingOperation($conn,$token,$user['id']);
    if ($existing) {
        if ($existing['tipo'] !== 'Venta' || $existing['sku'] !== $sku || (int)$existing['cantidad'] !== $qty || $existing['modo'] !== $mode || $existing['fecha'] !== $date) throw new DomainException('La operación ya se utilizó con otros datos.');
        $record=$existing['id'];
    } else {
        $stmt=$conn->prepare('SELECT * FROM articulo WHERE sku=?'); $stmt->execute([$sku]); $products=$stmt->fetchAll(PDO::FETCH_ASSOC);
        if (count($products)!==1) throw new DomainException('El código no existe o está duplicado. Revisa el catálogo.');
        $product=$products[0];
        if (array_key_exists('producto_id',$_POST) && filter_var($_POST['producto_id'],FILTER_VALIDATE_INT) !== (int)$product['id_articulo']) {
            $conn->rollBack();http_response_code(409);
            exit(json_encode(['message'=>'El producto cambió. Actualízalo en el pedido antes de confirmar.','code'=>'PRODUCTO_CAMBIO']));
        }
        if (array_key_exists('precio_confirmado',$_POST) && normalizeSalePrice($_POST['precio_confirmado']) !== normalizeSalePrice((string)$product['precio'])) {
            $conn->rollBack();http_response_code(409);
            exit(json_encode(['message'=>'El precio de '.$product['nombre'].' cambió. Actualízalo en el pedido antes de confirmar.','code'=>'PRECIO_CAMBIO']));
        }
        if ($product['categoria']==='Arreglos') {
            $allowed = $mode==='Por encargo' ? ['Por encargo','Ambas'] : ['Preparado','Ambas'];
            if (!in_array($product['modalidad'],$allowed,true)) throw new DomainException('La modalidad de venta no está habilitada para este arreglo.');
        } elseif ($mode!=='Preparado') throw new DomainException('Solo los arreglos pueden venderse por encargo.');
        $record=recordOperation($conn,$product,$qty,'Venta',$user['id'],$date,$token,$mode);
        recordSalePrice($conn,$record,$product['precio'],saleCurrency());
        if ($mode==='Por encargo') consumeRecipe($conn,$product,$qty,$record);
        else changeStock($conn,$product,-$qty,$record);
    }
    $stmt=$conn->prepare('SELECT precio_unitario,importe,moneda FROM registros WHERE id=?');$stmt->execute([$record]);$receipt=$stmt->fetch(PDO::FETCH_ASSOC);
    $conn->commit(); http_response_code(201); echo json_encode(['message'=>'OK']+$receipt);
} catch (DomainException $e) {
    if (isset($conn) && $conn->inTransaction()) $conn->rollBack();
    http_response_code(409); echo json_encode(['message'=>$e->getMessage()]);
} catch (PDOException $e) {
    if (isset($conn) && $conn->inTransaction()) $conn->rollBack();
    http_response_code(500); echo json_encode(['message'=>'No se pudo guardar la venta. Comprueba que la base esté actualizada.']);
}
