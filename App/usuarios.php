<?php
require_once __DIR__ . '/backend/auth.php';
$currentUser = require_access(['Administrador'], false);
require_once __DIR__ . '/backend/cuentas.php';
function esc($v) { return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8'); }
if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = operationToken();
$conn=inventoryDb();$error='';$message='';$editing=null;
$showForm=isset($_GET['nuevo'])||isset($_GET['editar']);
if ($_SERVER['REQUEST_METHOD']==='POST') {
    csrfCheck();
    try {
        $result=mutateAccount($conn,(int)$currentUser['id'],(int)$currentUser['version_sesion'],$_POST);
        if ($result['id']===(int)$currentUser['id']) {
            $_SESSION=[];session_regenerate_id(true);
            header('Location: login.html?cuenta=actualizada',true,303);exit;
        }
        $_SESSION['account_message']=$result['message'];header('Location: usuarios.php',true,303);exit;
    } catch (DomainException $e) { http_response_code(422);$error=$e->getMessage(); }
      catch (PDOException $e) { http_response_code(500);$error='No se pudo guardar la cuenta. Comprueba la actualización de la base e inténtalo de nuevo.'; }
    if (($_POST['accion']??'crear')!=='estado') {
        $showForm=true;
        $editing=['id'=>filter_var($_POST['id']??0,FILTER_VALIDATE_INT)?:0,'username'=>is_string($_POST['username']??null)?$_POST['username']:'','rol'=>$_POST['rol']??'Cajero','version_sesion'=>filter_var($_POST['version']??0,FILTER_VALIDATE_INT)?:0];
    }
}
if (isset($_GET['editar']) && !$editing) {
    $id=filter_var($_GET['editar'],FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
    $stmt=$conn->prepare('SELECT id,username,rol,activo,version_sesion FROM usuarios WHERE id=?');$stmt->execute([$id?:0]);$editing=$stmt->fetch(PDO::FETCH_ASSOC);
    if (!$editing) { http_response_code(404);exit('La cuenta no existe.'); }
}
$isEditing=!empty($editing['id']);
if (isset($_SESSION['account_message'])) { $message=$_SESSION['account_message'];unset($_SESSION['account_message']); }
$q=is_string($_GET['q']??null)?trim($_GET['q']):'';
$stmt=$conn->prepare('SELECT id,username,rol,activo,version_sesion FROM usuarios WHERE username LIKE ? ORDER BY activo DESC,username');$stmt->execute(['%'.$q.'%']);$accounts=$stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Administrar usuarios · Florería</title>
    <link rel="stylesheet" href="css/usuarios.css?v=<?= filemtime(__DIR__ . '/css/usuarios.css') ?>">
    <link rel="stylesheet" href="css/catalogo.css?v=<?= filemtime(__DIR__.'/css/catalogo.css') ?>">
    <link rel="stylesheet" href="css/cuentas.css?v=<?= filemtime(__DIR__.'/css/cuentas.css') ?>">
</head>
<body>
<main class="catalog-page clean-workspace accounts-page <?= $showForm?'movement-page':'' ?>">
    <a class="back-link" href="<?= $showForm?'usuarios.php':'menu.php' ?>"><?= $showForm?'Volver a usuarios':'Volver al menú' ?></a>
    <header class="catalog-header">
        <div>
            <span class="eyebrow">FLORERÍA / ADMINISTRACIÓN</span>
            <h1><?= $showForm?($isEditing?'Editar cuenta':'Crear usuario'):'Administrar usuarios' ?></h1>
        </div>
        <?php if (!$showForm): ?><a class="action" href="usuarios.php?nuevo=1">Nuevo usuario</a><?php endif; ?>
    </header>
    <?php if ($error): ?><p class="notice notice-error" role="alert"><?= esc($error) ?></p><?php endif; ?>
    <?php if ($message): ?><p class="notice notice-success" role="status"><?= esc($message) ?></p><?php endif; ?>
    <?php if ($showForm): ?>
    <section class="user-card">
        <form method="post" class="product-form account-form">
            <input type="hidden" name="csrf" value="<?= esc($_SESSION['csrf']) ?>">
            <input type="hidden" name="accion" value="<?= $isEditing?'editar':'crear' ?>">
            <input type="hidden" name="id" value="<?= (int)($editing['id']??0) ?>">
            <input type="hidden" name="version" value="<?= (int)($editing['version_sesion']??0) ?>">
            <div class="form-grid">
                <div class="field">
                    <label for="new-username">Nombre de usuario</label>
                    <input id="new-username" name="username" maxlength="50" required autocomplete="off" value="<?= esc($editing['username']??'') ?>" placeholder="Ej. cajero01">
                </div>
                <div class="field">
                    <label for="new-role">Rol</label>
                    <select id="new-role" name="rol"><?php foreach (['Cajero','Administrador'] as $role): ?><option <?= ($editing['rol']??'Cajero')===$role?'selected':'' ?>><?= $role ?></option><?php endforeach; ?></select>
                </div>
            </div>
            <?php if ($isEditing): ?>
            <details class="account-credentials" <?= $error?'open':'' ?>>
                <summary>Cambiar contraseña <span>Opcional</span></summary>
                <div class="credentials-fields">
                    <div class="field">
                        <label for="new-password">Nueva contraseña</label>
                        <input id="new-password" name="password" type="password" minlength="8" maxlength="72" autocomplete="new-password" placeholder="Al menos 8 caracteres">
                    </div>
                    <div class="field">
                        <label for="password-confirm">Repetir nueva contraseña</label>
                        <input id="password-confirm" name="password_confirm" type="password" maxlength="72" autocomplete="new-password">
                    </div>
                </div>
                <p class="field-note">Deja ambos campos vacíos para conservar la contraseña actual.</p>
            </details>
            <p class="account-session-note"><?= (int)$editing['id']===(int)$currentUser['id']?'Al guardar, tendrás que iniciar sesión nuevamente.':'Al guardar, esta cuenta deberá volver a iniciar sesión.' ?></p>
            <?php else: ?>
            <div class="field account-password">
                <label for="new-password">Contraseña</label>
                <input id="new-password" name="password" type="password" minlength="8" maxlength="72" autocomplete="new-password" required placeholder="Al menos 8 caracteres">
            </div>
            <?php endif; ?>
            <div class="form-actions">
                <a class="back-link" href="usuarios.php">Cancelar</a>
                <button><?= $isEditing?'Guardar cambios':'Crear usuario' ?></button>
            </div>
        </form>
    </section>
    <?php else: ?>
    <input type="hidden" name="csrf" value="<?= esc($_SESSION['csrf']) ?>">
    <section class="user-card accounts-card" aria-label="Cuentas del equipo">
        <div class="accounts-toolbar">
            <form class="search accounts-search" method="get">
                <label for="q">Buscar usuario</label>
                <div><input id="q" name="q" value="<?= esc($q) ?>" placeholder="Nombre de usuario"><button>Buscar</button></div>
            </form>
            <div class="accounts-count"><?= count($accounts) ?> <?= count($accounts)===1?'cuenta':'cuentas' ?><?php if ($q!==''): ?><a href="usuarios.php">Ver todas</a><?php endif; ?></div>
        </div>
        <div class="table-wrap">
            <table class="accounts-table">
                <thead><tr><th scope="col">Usuario</th><th scope="col">Rol</th><th scope="col">Estado</th><th scope="col">Acciones</th></tr></thead>
                <tbody>
                <?php foreach ($accounts as $account): ?>
                <tr>
                    <td><strong><?= esc($account['username']) ?></strong><?php if ((int)$account['id']===(int)$currentUser['id']): ?><small class="own-account">Tu cuenta</small><?php endif; ?></td>
                    <td><?= esc($account['rol']) ?></td>
                    <td><span class="account-status <?= $account['activo']?'':'account-inactive' ?>"><?= $account['activo']?'Activa':'Inactiva' ?></span></td>
                    <td><div class="row-actions">
                        <a class="edit-link" href="usuarios.php?editar=<?= (int)$account['id'] ?>">Editar</a>
                        <?php if ((int)$account['id']!==(int)$currentUser['id']): ?>
                        <form method="post" onsubmit="return confirm('<?= $account['activo']?'¿Desactivar esta cuenta? Se bloqueará su acceso y se conservará su historial.':'¿Activar esta cuenta? Podrá volver a iniciar sesión.' ?>')">
                            <input type="hidden" name="csrf" value="<?= esc($_SESSION['csrf']) ?>">
                            <input type="hidden" name="accion" value="estado">
                            <input type="hidden" name="id" value="<?= (int)$account['id'] ?>">
                            <input type="hidden" name="version" value="<?= (int)$account['version_sesion'] ?>">
                            <input type="hidden" name="activo" value="<?= $account['activo']?0:1 ?>">
                            <button class="delete-product"><?= $account['activo']?'Desactivar':'Activar' ?></button>
                        </form>
                        <?php endif; ?>
                    </div></td>
                </tr>
                <?php endforeach; ?>
                <?php if (!$accounts): ?><tr><td colspan="4" class="accounts-empty">No se encontraron cuentas. Prueba con otro nombre.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
    <p class="accounts-footer">Las cuentas desactivadas conservan su historial.</p>
    <?php endif; ?>
</main>
</body>
</html>
