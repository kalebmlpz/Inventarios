<?php
require_once __DIR__ . '/../backend/auth.php';
$user=require_access(['Administrador'],false);
require_once __DIR__ . '/../backend/inventario.php';
function esc($v) { return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8'); }
$conn=inventoryDb();
$id=filter_var($_GET['id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
if (!$id) { http_response_code(400); exit('Selecciona un arreglo del catálogo.'); }
try { $product=inventoryProduct($conn,$id); } catch (DomainException $e) { http_response_code(404); exit(esc($e->getMessage())); }
if ($product['categoria']!=='Arreglos') { http_response_code(400); exit('Esta opción solo corresponde a arreglos.'); }
if (empty($_SESSION['csrf'])) $_SESSION['csrf']=operationToken();
$message='';
if ($_SERVER['REQUEST_METHOD']==='POST') {
    csrfCheck();
    try {
        inventoryLock($conn); $product=inventoryProduct($conn,$id);
        if ($product['categoria']!=='Arreglos') throw new DomainException('El producto ya no es un arreglo.');
        if (($_POST['accion']??'')==='receta') {
            $ids=$_POST['material']??[]; $quantities=$_POST['cantidad']??[];
            if (!is_array($ids)||!is_array($quantities)||count($ids)!==count($quantities)||count($ids)>100) throw new DomainException('Revisa los componentes.');
            $items=[];
            foreach ($ids as $i=>$materialId) {
                if ($materialId==='') continue;
                $materialId=filter_var($materialId,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
                $qty=filter_var($quantities[$i]??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1,'max_range'=>2147483647]]);
                if (!$materialId||!$qty||isset($items[$materialId])) throw new DomainException('Usa cantidades enteras positivas y no repitas materiales.');
                $material=inventoryProduct($conn,$materialId);
                if ($material['categoria']==='Arreglos'||$materialId===$id) throw new DomainException('Selecciona flores, bases o accesorios; no otros arreglos.');
                $items[$materialId]=$qty;
            }
            if (!$items) throw new DomainException('Agrega al menos un componente.');
            $stmt=$conn->prepare('DELETE FROM componentes WHERE arreglo_id=?'); $stmt->execute([$id]);
            $stmt=$conn->prepare('INSERT INTO componentes VALUES(?,?,?)');
            foreach ($items as $materialId=>$qty) $stmt->execute([$id,$materialId,$qty]);
            $message='Componentes guardados. Se usarán para las próximas elaboraciones.';
        } elseif (($_POST['accion']??'')==='preparar') {
            $qty=filter_var($_POST['unidades']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1,'max_range'=>2147483647]]);
            $token=$_POST['operacion_id']??'';
            if (!$qty||!is_string($token)||!preg_match('/^[a-zA-Z0-9-]{16,64}$/',$token)) throw new DomainException('Introduce una cantidad válida.');
            $existing=existingOperation($conn,$token,$user['id']);
            if ($existing) {
                if ($existing['tipo']!=='Preparación'||$existing['sku']!==(string)$product['sku']||(int)$existing['cantidad']!==$qty) throw new DomainException('Operación ya utilizada.');
            } else {
                if (!in_array($product['modalidad'],['Preparado','Ambas'],true)) throw new DomainException('Este arreglo solo se elabora por encargo.');
                $record=recordOperation($conn,$product,$qty,'Preparación',$user['id'],date('Y-m-d'),$token);
                consumeRecipe($conn,$product,$qty,$record);
                changeStock($conn,$product,$qty,$record);
            }
            $message='Arreglos preparados. Se descontaron materiales y se sumaron unidades listas.';
        } else throw new DomainException('Acción no válida.');
        $conn->commit();
        $_SESSION['component_message']=$message;
        header('Location: componentes.php?id='.$id,true,303); exit;
    } catch (DomainException $e) { if ($conn->inTransaction()) $conn->rollBack(); $message=$e->getMessage(); }
      catch (PDOException $e) { if ($conn->inTransaction()) $conn->rollBack(); $message='No se pudo guardar. Inténtalo de nuevo.'; }
}
if (isset($_SESSION['component_message'])) { $message=$_SESSION['component_message']; unset($_SESSION['component_message']); }
$product=inventoryProduct($conn,$id);
$materials=$conn->query("SELECT * FROM articulo WHERE categoria <> 'Arreglos' ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);
$stmt=$conn->prepare('SELECT componente_id,cantidad FROM componentes WHERE arreglo_id=? ORDER BY componente_id'); $stmt->execute([$id]); $recipe=$stmt->fetchAll(PDO::FETCH_ASSOC);
if (!$recipe) $recipe=[['componente_id'=>'','cantidad'=>1]];
?>
<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Componentes del arreglo</title><link rel="stylesheet" href="../css/usuarios.css?v=<?= filemtime(__DIR__ . '/../css/usuarios.css') ?>"><link rel="stylesheet" href="../css/catalogo.css?v=<?= filemtime(__DIR__.'/../css/catalogo.css') ?>"></head><body><main class="catalog-page">
<a class="back-link" href="consulta.php">Volver al catálogo</a>
<header class="catalog-header"><div><span class="eyebrow">FLORERÍA / ARREGLOS</span><h1><?= esc($product['nombre']) ?></h1><p><?= esc($product['stock']) ?> arreglos listos · <?= esc($product['modalidad']) ?></p></div></header>
<?php if ($message): ?><p class="notice" role="status"><?= esc($message) ?></p><?php endif; ?>
<section class="user-card"><form method="post" class="product-form"><h2>Componentes por arreglo</h2><p class="field-note">Indica lo que necesitas para elaborar una unidad. Guardar esta lista no consume materiales.</p>
<input type="hidden" name="csrf" value="<?= esc($_SESSION['csrf']) ?>"><input type="hidden" name="accion" value="receta">
<div id="recipe-rows"><?php foreach ($recipe as $item): ?><div class="recipe-row"><label>Material<select name="material[]"><option value="">Seleccionar material</option><?php foreach ($materials as $material): ?><option value="<?= (int)$material['id_articulo'] ?>" <?= $item['componente_id']==$material['id_articulo']?'selected':'' ?>><?= esc($material['nombre'].' · '.$material['unidad'].' · disponibles: '.$material['stock']) ?></option><?php endforeach; ?></select></label><label>Cantidad<input name="cantidad[]" type="number" min="1" step="1" value="<?= (int)$item['cantidad'] ?>" required></label><button type="button" class="remove-material">Quitar</button></div><?php endforeach; ?></div>
<div class="form-actions"><button type="button" id="add-material">Agregar material</button><button>Guardar componentes</button></div></form></section>
<?php if (in_array($product['modalidad'],['Preparado','Ambas'],true)): ?>
<section class="user-card catalog-list"><form method="post" class="product-form"><h2>Preparar arreglos</h2><p class="field-note">Se consumen los componentes guardados y se suman arreglos listos. Si falta un material, no se modifica ninguna existencia.</p><input type="hidden" name="csrf" value="<?= esc($_SESSION['csrf']) ?>"><input type="hidden" name="accion" value="preparar"><input type="hidden" name="operacion_id" value="<?= operationToken() ?>"><label>Unidades a preparar<input name="unidades" type="number" min="1" step="1" value="1" required></label><button class="prepare-button">Preparar arreglos</button></form></section>
<?php endif; ?>
</main><script src="js/componentes.js" defer></script></body></html>
