<?php
require_once __DIR__ . '/inventario.php';
function reportDefinitions() {
    return [
        'ventas' => ['titulo'=>'Ventas netas', 'columnas'=>['Código','Producto','Unidad','Vendidas','Corregidas','Netas'], 'nota'=>'Agrupa las ventas del periodo. Las correcciones se cuentan hasta hoy, aunque se hayan registrado después de la venta. No representa importes de dinero.'],
        'consumo' => ['titulo'=>'Consumo de materiales', 'columnas'=>['Código','Material','Unidad','Consumido','Reintegrado','Neto del periodo'], 'nota'=>'Materiales consumidos al preparar arreglos o vender por encargo. Los reintegros corresponden a capturas corregidas en estas fechas. El neto puede ser negativo si se revierte un consumo de otro periodo.'],
        'mermas' => ['titulo'=>'Mermas', 'columnas'=>['Código','Producto','Unidad','Unidades perdidas','Operaciones'], 'nota'=>'Pérdidas registradas durante el periodo. Cada unidad se informa por separado: no se suman tallos, paquetes y arreglos como si fueran equivalentes.'],
        'movimientos' => ['titulo'=>'Movimientos', 'columnas'=>['Folio','Fecha','Tipo','Código','Producto','Cantidad','Unidad','Responsable','Motivo','Venta original'], 'nota'=>'Detalle de las operaciones registradas en estas fechas. Las ventas originales y sus correcciones se conservan como operaciones separadas.'],
    ];
}
function reportDates($start, $end) {
    foreach ([$start,$end] as $date) {
        $parsed = is_string($date) ? DateTimeImmutable::createFromFormat('!Y-m-d',$date) : false;
        if (!$parsed || $parsed->format('Y-m-d') !== $date) throw new DomainException('Selecciona fechas válidas.');
    }
    if ($start > $end) throw new DomainException('La fecha Desde debe ser anterior o igual a Hasta.');
}
function reportGroupKey($row) {
    // Las unidades desconocidas o modificadas no se mezclan con otras unidades.
    return json_encode([$row['articulo_id'] ?? [$row['sku'],$row['nombre']], $row['unidad_snapshot']]);
}
function loadReport($conn, $kind, $start, $end) {
    reportDates($start,$end);
    if (!isset(reportDefinitions()[$kind])) throw new DomainException('Selecciona un reporte válido.');
    $conn->beginTransaction();
    try {
        if ($kind === 'movimientos') {
            $stmt=$conn->prepare("SELECT r.*,u.username FROM registros r LEFT JOIN usuarios u ON u.id=r.usuario_id WHERE r.fecha BETWEEN ? AND ? ORDER BY r.fecha DESC,r.id DESC");
            $stmt->execute([$start,$end]); $rows=[];
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $rows[]=[$r['id'],$r['fecha'],$r['tipo'],$r['sku'],$r['nombre_snapshot'] ?? 'Sin nombre registrado',(int)$r['cantidad'],$r['unidad_snapshot'] ?? 'Sin unidad registrada',$r['username'] ?? 'Sin autor registrado',$r['motivo'] ?? '',$r['venta_origen_id'] ?? ''];
            }
        } else {
            if ($kind === 'ventas') {
                $query="SELECT r.*,COALESCE(r.nombre_snapshot,'Sin nombre registrado') AS nombre,(SELECT COALESCE(SUM(c.cantidad),0) FROM registros c WHERE c.venta_origen_id=r.id AND c.tipo IN ('Cancelación','Devolución')) AS corregidas FROM registros r WHERE r.tipo='Venta' AND r.fecha BETWEEN ? AND ? ORDER BY r.id DESC";
            } elseif ($kind === 'consumo') {
                $query="SELECT m.* FROM movimientos m JOIN registros r ON r.id=m.registro_id LEFT JOIN registros original ON original.id=r.venta_origen_id WHERE r.fecha BETWEEN ? AND ? AND ((m.cantidad<0 AND (r.tipo='Preparación' OR (r.tipo='Venta' AND r.modo='Por encargo'))) OR (m.cantidad>0 AND r.tipo='Cancelación' AND r.tratamiento='captura' AND original.modo='Por encargo')) ORDER BY m.id DESC";
            } else {
                $query="SELECT r.*,COALESCE(r.nombre_snapshot,'Sin nombre registrado') AS nombre FROM registros r WHERE r.tipo='Merma' AND r.fecha BETWEEN ? AND ? ORDER BY r.id DESC";
            }
            $stmt=$conn->prepare($query); $stmt->execute([$start,$end]); $groups=[];
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $key=reportGroupKey($r);
                if (!isset($groups[$key])) $groups[$key]=[$r['sku'],$r['nombre'],$r['unidad_snapshot'] ?? 'Sin unidad registrada',0,0];
                if ($kind === 'ventas') { $groups[$key][3]+=(int)$r['cantidad']; $groups[$key][4]+=(int)$r['corregidas']; }
                elseif ($kind === 'consumo') {
                    if ((int)$r['cantidad']<0) $groups[$key][3]+=abs((int)$r['cantidad']);
                    else $groups[$key][4]+=(int)$r['cantidad'];
                } else { $groups[$key][3]+=(int)$r['cantidad']; $groups[$key][4]++; }
            }
            $rows=array_values($groups);
            foreach ($rows as &$row) if ($kind !== 'mermas') $row[]=$row[3]-$row[4];
            unset($row);
            usort($rows,fn($a,$b)=>strnatcasecmp($a[1],$b[1]));
        }
        $conn->commit(); return $rows;
    } catch (Throwable $e) { if ($conn->inTransaction()) $conn->rollBack(); throw $e; }
}
function reportCsvValue($value) {
    if (is_int($value) || is_float($value)) return $value;
    $text=(string)$value;
    // Excel debe interpretar los nombres/motivos como texto, no como fórmulas.
    if (preg_match('/^[\x00-\x20]*[=+@-]/u',$text) || preg_match('/^[\t\r\n]/u',$text)) return "'".$text;
    return $text;
}
