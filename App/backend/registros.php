<?php
require_once __DIR__.'/auth.php';
$user=require_access();
require_once __DIR__.'/inventario.php';
header('Content-Type: application/json; charset=utf-8'); header('Cache-Control: no-store');
$date=$_GET['fecha']??'';
$parsed=is_string($date)?DateTimeImmutable::createFromFormat('!Y-m-d',$date):false;
if ($_SERVER['REQUEST_METHOD']!=='GET'||!$parsed||$parsed->format('Y-m-d')!==$date) { http_response_code(400); exit(json_encode(['message'=>'Selecciona una fecha válida'])); }
try {
    $conn=inventoryDb();
    $query="SELECT r.*, (SELECT COALESCE(SUM(c.cantidad),0) FROM registros c WHERE c.venta_origen_id=r.id AND c.tipo IN ('Cancelación','Devolución')) AS cantidad_corregida,COALESCE(r.nombre_snapshot,a.nombre) AS nombre,u.username AS responsable FROM registros r LEFT JOIN (SELECT sku,MAX(nombre) AS nombre FROM articulo GROUP BY sku) a ON a.sku=r.sku LEFT JOIN usuarios u ON u.id=r.usuario_id WHERE r.fecha=?";
    $params=[$date]; if ($user['rol']==='Cajero') { $query.=' AND r.usuario_id=?'; $params[]=$user['id']; }
    $query.=' ORDER BY r.id DESC'; $stmt=$conn->prepare($query); $stmt->execute($params); $records=$stmt->fetchAll(PDO::FETCH_ASSOC);
    $stmt=$conn->prepare('SELECT m.* FROM movimientos m JOIN registros r ON r.id=m.registro_id WHERE r.fecha=?'.($user['rol']==='Cajero'?' AND r.usuario_id=?':'').' ORDER BY m.id');
    $stmt->execute($params); $details=[];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) $details[$row['registro_id']][]=$row;
    foreach ($records as &$record) $record['movimientos']=$details[$record['id']]??[];
    echo json_encode($records);
} catch (PDOException $e) { http_response_code(500); echo json_encode(['message'=>'No se pudo consultar el historial. Comprueba la actualización de la base.']); }
