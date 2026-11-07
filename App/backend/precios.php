<?php
function saleCurrency() { return 'MXN'; }
function normalizeSalePrice($value) {
    if (!is_string($value) || !preg_match('/^(\d{1,8})(?:\.(\d{1,2}))?$/',$value,$parts)) throw new DomainException('El precio no es válido.');
    return (ltrim($parts[1],'0') ?: '0').'.'.str_pad($parts[2]??'',2,'0');
}
function recordSalePrice($conn,$id,$price,$currency) {
    $price=normalizeSalePrice((string)$price);
    if (!preg_match('/^[A-Z]{3}$/',$currency)) throw new DomainException('La moneda no es válida.');
    // DECIMAL mantiene los centavos exactos, incluso en cantidades grandes.
    $stmt=$conn->prepare('UPDATE registros SET precio_unitario=?,importe=CAST(? AS DECIMAL(10,2))*cantidad,moneda=? WHERE id=?');
    $stmt->execute([$price,$price,$currency,$id]);
}
function moneyAmount($value) {
    if ($value===null) return 'Sin precio registrado';
    if (!preg_match('/^(-?)(\d+)(?:\.(\d+))?$/',(string)$value,$parts)) return '—';
    $whole=preg_replace('/\B(?=(\d{3})+(?!\d))/',',',$parts[2]);
    return $parts[1].'$'.$whole.'.'.str_pad(substr($parts[3]??'',0,2),2,'0');
}
