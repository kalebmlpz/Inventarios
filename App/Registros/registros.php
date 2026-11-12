<?php
require_once __DIR__.'/../backend/auth.php';
$user=require_access(['Administrador','Cajero'],false);
?>
<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Historial · Florería</title><link rel="stylesheet" href="../css/usuarios.css?v=<?= filemtime(__DIR__ . '/../css/usuarios.css') ?>"><link rel="stylesheet" href="../css/catalogo.css?v=<?= filemtime(__DIR__.'/../css/catalogo.css') ?>"></head><body><main class="catalog-page clean-workspace">
<a class="back-link" href="../menu.php">Volver al menú</a><header class="catalog-header"><div><span class="eyebrow">FLORERÍA / HISTORIAL</span><h1><?= $user['rol']==='Cajero'?'Mis operaciones':'Registros' ?></h1></div></header>
<?php if (isset($_SESSION['sale_correction_message'])): ?><p class="notice notice-success" role="status"><?= htmlspecialchars($_SESSION['sale_correction_message'],ENT_QUOTES,'UTF-8') ?> La corrección se registra con fecha de hoy.</p><?php unset($_SESSION['sale_correction_message']); endif; ?>
<section class="user-card"><form id="formulario-articulos" class="search"><label for="fecha">Fecha</label><div><input type="date" id="fecha" required><button>Consultar</button></div></form><div class="table-wrap"><table id="tabla" data-admin="<?= $user['rol']==='Administrador'?'1':'0' ?>"></table></div></section>
</main><script src="../js/dinero.js?v=<?= filemtime(__DIR__.'/../js/dinero.js') ?>"></script><script src="js/registros.js?v=<?= filemtime(__DIR__.'/js/registros.js') ?>" defer></script></body></html>
