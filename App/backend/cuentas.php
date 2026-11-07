<?php
require_once __DIR__ . '/inventario.php';
function accountLock($conn) {
    $conn->beginTransaction();
    $conn->query('SELECT id FROM cuentas_lock WHERE id=1 FOR UPDATE')->fetch();
}
function accountPassword($password) {
    if (!is_string($password) || strlen($password)<8) throw new DomainException('La contraseña debe tener al menos 8 caracteres.');
    if (str_contains($password, "\0")) throw new DomainException('Contraseña no válida.');
    if (strlen($password)>72) throw new DomainException('La contraseña es demasiado larga; usa una más corta.');
    return password_hash($password,PASSWORD_DEFAULT);
}
function mutateAccount($conn,$actorId,$actorVersion,$input) {
    $action=$input['accion']??'crear';
    if (!in_array($action,['crear','editar','estado'],true)) throw new DomainException('Acción no válida.');
    try {
        accountLock($conn);
        $stmt=$conn->prepare('SELECT id,rol,activo,version_sesion FROM usuarios WHERE id=?');
        $stmt->execute([$actorId]);$actor=$stmt->fetch(PDO::FETCH_ASSOC);
        if (!$actor || !$actor['activo'] || $actor['rol']!=='Administrador' || (int)$actor['version_sesion']!==$actorVersion) throw new DomainException('Tu cuenta cambió. Vuelve a iniciar sesión.');
        $target=null;$id=null;
        if ($action!=='crear') {
            $id=filter_var($input['id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
            $version=filter_var($input['version']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>0]]);
            if (!$id || $version===false || $version===null) throw new DomainException('Selecciona una cuenta válida.');
            $stmt=$conn->prepare('SELECT id,username,rol,activo,version_sesion FROM usuarios WHERE id=?');
            $stmt->execute([$id]);$target=$stmt->fetch(PDO::FETCH_ASSOC);
            if (!$target) throw new DomainException('La cuenta ya no existe.');
            if ((int)$target['version_sesion']!==$version) throw new DomainException('Esta cuenta cambió desde que abriste la página. Recarga y vuelve a intentarlo.');
        }
        if ($action==='estado') {
            $active=filter_var($input['activo']??null,FILTER_VALIDATE_INT);
            if (!in_array($active,[0,1],true)) throw new DomainException('Estado no válido.');
            if ($id===$actorId && !$active) throw new DomainException('No puedes desactivar tu propia cuenta.');
            $role=$target['rol'];
        } else {
            $username=is_string($input['username']??null)?trim($input['username']):'';
            $role=$input['rol']??'';
            if (!preg_match('/^.{1,50}$/us',$username) || preg_match('/[\x00-\x1F\x7F]/',$username) || !in_array($role,['Administrador','Cajero'],true)) throw new DomainException('Introduce un usuario de hasta 50 caracteres y un rol válido.');
            $stmt=$conn->prepare('SELECT id FROM usuarios WHERE username=? AND id<>?');
            $stmt->execute([$username,$id??0]);
            if ($stmt->fetch()) throw new DomainException('Ese nombre de usuario ya existe.');
            $active=$target ? (int)$target['activo'] : 1;
        }
        if ($target && $target['rol']==='Administrador' && $target['activo'] && ($role!=='Administrador' || !$active)) {
            $admins=(int)$conn->query("SELECT COUNT(*) FROM usuarios WHERE rol='Administrador' AND activo=1")->fetchColumn();
            if ($admins<=1) throw new DomainException('Debe quedar al menos un Administrador activo. Crea o activa otra cuenta de Administrador primero.');
        }
        if ($action==='crear') {
            $hash=accountPassword($input['password']??null);
            $stmt=$conn->prepare('INSERT INTO usuarios(username,password,rol) VALUES(?,?,?)');
            $stmt->execute([$username,$hash,$role]);$id=(int)$conn->lastInsertId();
            $message='Usuario creado correctamente.';
        } elseif ($action==='editar') {
            $password=$input['password']??'';
            if (!is_string($password)) throw new DomainException('Contraseña no válida.');
            $params=[$username,$role];$query='UPDATE usuarios SET username=?,rol=?,version_sesion=version_sesion+1';
            if ($password!=='') {
                if (!is_string($input['password_confirm']??null) || !hash_equals($password,$input['password_confirm'])) throw new DomainException('Las contraseñas no coinciden.');
                $params[]=accountPassword($password);$query.=',password=?';
            }
            $query.=' WHERE id=?';$params[]=$id;$stmt=$conn->prepare($query);$stmt->execute($params);
            $message='Cuenta actualizada. Sus sesiones anteriores se cerraron.';
        } else {
            $stmt=$conn->prepare('UPDATE usuarios SET activo=?,version_sesion=version_sesion+1 WHERE id=?');
            $stmt->execute([$active,$id]);$message=$active?'Cuenta activada. Puede iniciar sesión.':'Cuenta desactivada. Su historial se conserva.';
        }
        $conn->commit();return ['id'=>$id,'message'=>$message];
    } catch (Throwable $e) { if ($conn->inTransaction()) $conn->rollBack();throw $e; }
}
function loginAccount($conn,$username,$password) {
    try {
        accountLock($conn);
        $stmt=$conn->prepare('SELECT id,password,rol,activo,version_sesion FROM usuarios WHERE username=?');
        $stmt->execute([$username]);$user=$stmt->fetch(PDO::FETCH_ASSOC);
        $info=$user?password_get_info($user['password']):null;
        $legacy=$user && $info['algo']===null;
        $valid=$user && ($legacy?hash_equals($user['password'],$password):password_verify($password,$user['password']));
        if ($user && !$legacy && ($info['algoName'] ?? '') === 'bcrypt' && strlen($password)>72) $valid=false;
        if (!$valid || !$user['activo'] || !in_array($user['rol'],['Administrador','Cajero'],true)) {
            $conn->rollBack();return null;
        }
        if (($legacy && strlen($password)<=72) || (!$legacy && password_needs_rehash($user['password'],PASSWORD_DEFAULT))) {
            $stmt=$conn->prepare('UPDATE usuarios SET password=? WHERE id=?');
            $stmt->execute([password_hash($password,PASSWORD_DEFAULT),$user['id']]);
        }
        $conn->commit();unset($user['password']);return $user;
    } catch (Throwable $e) { if ($conn->inTransaction()) $conn->rollBack();throw $e; }
}
