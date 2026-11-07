"""Account lifecycle, session revocation and isolated last-admin concurrency checks."""
import urllib.request as u, urllib.parse as p, urllib.error as e, http.cookiejar, re, subprocess, uuid, json
from concurrent.futures import ThreadPoolExecutor
base='http://127.0.0.1:8080/'
def client():return u.build_opener(u.HTTPCookieProcessor(http.cookiejar.CookieJar()))
def call(c,path,data=None):
 req=u.Request(base+path,data=p.urlencode(data).encode() if data is not None else None)
 try:
  r=c.open(req);return r.status,r.read().decode(),r.url
 except e.HTTPError as err:return err.code,err.read().decode(),err.url

def sql(q):return subprocess.check_output(['docker','compose','-f','/workspace/inventarios-env/compose.yaml','exec','-T','db','mysql','-uroot','punto_de_venta','-N','-e',q],text=True).strip()
a=client();target=client();old=client();fresh=client();tag=uuid.uuid4().hex[:12];name='test_'+tag;renamed=name+'_nuevo';pid=None;db='cuentas_test_'+tag
assert call(a,'backend/login.php',dict(username='Admin',password='Admin123'))[0]==200
csrf=re.search('name="csrf" value="([^"]+)"',call(a,'usuarios.php')[1])[1]
def version():return int(sql('SELECT version_sesion FROM usuarios WHERE id='+str(pid)))
def edit(**overrides):
 values=dict(accion='editar',id=pid,version=version(),csrf=csrf,username=renamed,rol='Administrador',password='')
 values.update(overrides);return call(a,'usuarios.php',values)
def state(active):return call(a,'usuarios.php',dict(accion='estado',id=pid,version=version(),csrf=csrf,activo=active))
try:
 assert 'Usuario creado correctamente' in call(a,'usuarios.php',dict(csrf=csrf,username=name,rol='Cajero',password='Inicial123'))[1]
 pid=int(sql("SELECT id FROM usuarios WHERE username='"+name+"'"))
 assert call(target,'backend/login.php',dict(username=name,password='Inicial123'))[0]==200
 assert call(old,'backend/login.php',dict(username=name,password='Inicial123'))[0]==200
 stored=sql('SELECT password FROM usuarios WHERE id='+str(pid));assert stored!='Inicial123'
 assert call(fresh,'backend/login.php',dict(username=name,password=stored))[0]==401, 'Hash literal authenticated'
 assert 'Cuenta actualizada' in edit()[1]
 assert call(old,'backend/consulta.php')[0]==401
 assert call(target,'backend/consulta.php')[0]==401
 assert call(target,'backend/login.php',dict(username=renamed,password='Inicial123'))[0]==200
 assert call(target,'usuarios.php')[0]==200
 assert edit(version=0,username='stale_'+tag)[0]==422
 assert sql('SELECT username FROM usuarios WHERE id='+str(pid))==renamed
 v=version();assert edit(password='Nueva1234',password_confirm='distinta')[0]==422 and version()==v
 assert edit(password='abc\x00def123',password_confirm='abc\x00def123')[0]==422
 assert 'Cuenta actualizada' in edit(password='Nueva1234',password_confirm='Nueva1234')[1]
 assert call(target,'backend/consulta.php')[0]==401
 assert call(target,'backend/login.php',dict(username=renamed,password='Inicial123'))[0]==401
 assert call(target,'backend/login.php',dict(username=renamed,password='Nueva1234'))[0]==200
 assert 'Cuenta desactivada' in state(0)[1]
 assert call(target,'backend/consulta.php')[0]==401
 assert call(fresh,'backend/login.php',dict(username=renamed,password='Nueva1234'))[0]==401
 assert 'Cuenta activada' in state(1)[1]
 assert call(old,'backend/consulta.php')[0]==401, 'Old session revived after reactivation'
 assert call(target,'backend/login.php',dict(username=renamed,password='Nueva1234'))[0]==200
 owncsrf=re.search('name="csrf" value="([^"]+)"',call(target,'usuarios.php')[1])[1]
 assert call(target,'usuarios.php',dict(csrf=owncsrf,accion='estado',id=pid,version=version(),activo=0))[0]==422
 assert call(a,'usuarios.php',dict(csrf='incorrecto',accion='estado',id=pid,version=version(),activo=0))[0]==403
 assert call(a,'usuarios.php',dict(csrf=csrf,accion='eliminar',id=pid))[0]==422
 assert edit(username='Admin')[0]==422
 # A self-service password change signs out that session and requires new credentials.
 result=call(target,'usuarios.php',dict(csrf=owncsrf,accion='editar',id=pid,version=version(),username=renamed,rol='Administrador',password='Propia123',password_confirm='Propia123'))
 assert 'login.html?cuenta=actualizada' in result[2]
 assert call(target,'backend/consulta.php')[0]==401
 assert call(target,'backend/login.php',dict(username=renamed,password='Propia123'))[0]==200
 # Legacy plaintext accounts are upgraded on login without accepting their resulting hash as password.
 sql("UPDATE usuarios SET password='Legada123' WHERE id="+str(pid))
 assert call(fresh,'backend/login.php',dict(username=renamed,password='Legada123'))[0]==200
 upgraded=sql('SELECT password FROM usuarios WHERE id='+str(pid));assert upgraded!='Legada123'
 assert call(client(),'backend/login.php',dict(username=renamed,password=upgraded))[0]==401
 assert upgraded not in call(a,'usuarios.php?editar='+str(pid))[1]
 assert 'Cuenta actualizada' in edit(rol='Cajero')[1]
 assert call(fresh,'backend/consulta.php')[0]==401
 assert call(fresh,'backend/login.php',dict(username=renamed,password='Legada123'))[0]==200
 assert call(fresh,'usuarios.php')[0]==403
 assert call(fresh,'usuarios.php',dict(csrf=csrf,username='blocked',rol='Administrador',password='Temporal123'))[0]==403
 # Isolate destructive last-admin scenarios from actual development accounts.
 sql('CREATE DATABASE '+db+'; CREATE TABLE '+db+'.usuarios LIKE usuarios; CREATE TABLE '+db+'.cuentas_lock LIKE cuentas_lock; INSERT INTO '+db+'.cuentas_lock VALUES(1)')
 sql("INSERT INTO "+db+".usuarios(id,username,password,rol,activo) VALUES(1,'Owner','Unused123','Administrador',1),(2,'Cashier','Unused123','Cajero',0),(3,'Other','Unused123','Administrador',0)")
 code="require '/app/backend/cuentas.php'; $x=json_decode($argv[1],true); $c=inventoryDb(); $c->exec('USE '+$x['db']); try { mutateAccount($c,$x['actor'],$x['version'],$x['post']); echo 'OK'; } catch (DomainException $e) { echo 'REJECTED'; }"
 # PHP uses dot for concatenation, unlike Python.
 code=code.replace("'USE '+$x['db']", "'USE '.$x['db']")
 def mutate(actor,actor_version,post):
  args=dict(db=db,actor=actor,version=actor_version,post=post)
  return subprocess.check_output(['docker','compose','-f','/workspace/inventarios-env/compose.yaml','exec','-T','web','php','-d','pdo_mysql.default_socket=/var/run/mysqld/mysqld.sock','-r',code,json.dumps(args)],text=True)
 assert mutate(1,0,dict(accion='editar',id=1,version=0,username='Owner',rol='Cajero',password=''))=='REJECTED'
 assert mutate(1,0,dict(accion='estado',id=1,version=0,activo=0))=='REJECTED'
 assert mutate(1,0,dict(accion='estado',id=3,version=0,activo=1))=='OK'
 jobs=[(1,0,dict(accion='editar',id=3,version=1,username='Other',rol='Cajero',password='')),(3,1,dict(accion='editar',id=1,version=0,username='Owner',rol='Cajero',password=''))]
 with ThreadPoolExecutor(2) as pool:results=list(pool.map(lambda x:mutate(*x),jobs))
 assert sorted(results)==['OK','REJECTED'],results
 assert sql("SELECT COUNT(*) FROM "+db+".usuarios WHERE rol='Administrador' AND activo=1")=='1'
 print('PASS: cuentas, roles, contraseñas, sesiones revocadas, activación, CSRF, datos obsoletos y último Administrador bajo concurrencia')
finally:
 if pid:sql('DELETE FROM usuarios WHERE id='+str(pid))
 sql('DROP DATABASE IF EXISTS '+db)
