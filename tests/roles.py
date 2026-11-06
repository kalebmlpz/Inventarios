"""Integration check against a running development server; uses disposable users/data."""
import urllib.request as u, urllib.parse as p, urllib.error as e, http.cookiejar, json, re, subprocess, uuid
base='http://127.0.0.1:8080/'
def client():return u.build_opener(u.HTTPCookieProcessor(http.cookiejar.CookieJar()))
def call(c,path,data=None,method=None):
 req=u.Request(base+path,data=p.urlencode(data).encode() if data is not None else None,method=method)
 try:
  r=c.open(req);return r.status,r.read().decode()
 except e.HTTPError as err:return err.code,err.read().decode()
def sql(query):
 return subprocess.check_output(['docker','compose','-f','/workspace/inventarios-env/compose.yaml','exec','-T','db','mysql','-uroot','punto_de_venta','-N','-e',query],text=True)
admin=client();anon=client();cash=client();name='test_'+uuid.uuid4().hex[:16]
assert call(anon,'backend/consulta.php')[0]==401
assert call(admin,'backend/login.php',dict(username='Admin',password='Admin123'))[0]==200
try:
 page=call(admin,'usuarios.php')[1];token=re.search('name="csrf" value="([^"]+)"',page)[1]
 assert 'Usuario creado correctamente' in call(admin,'usuarios.php',dict(csrf=token,username=name,password='Temporal123',rol='Cajero'))[1]
 assert call(cash,'backend/login.php',dict(username=name,password='Temporal123',rol='Administrador'))[0]==200
 assert 'Mis operaciones' in call(cash,'menu.php')[1]
 for path in ['usuarios.php','Articulos/alta.php','Articulos/cambio.php','Articulos/baja.php']:
  assert call(cash,path)[0]==403,path
 assert call(cash,'backend/alta.php',dict(sku='123'))[0]==403
 assert call(cash,'backend/eliminar_producto.php',dict(id=1,csrf='incorrecto'))[0]==403
 assert call(cash,'backend/baja.php?sku=123',method='DELETE')[0]==403
 assert call(cash,'backend/consulta.php?sku=123456')[0]==200
 assert call(cash,'Articulos/consulta.php')[0]==200
 assert call(admin,'Articulos/alta.php')[0]==200
 # Isolate operation attribution with a unique SKU; cleanup only this test's records.
 sku=str(int(uuid.uuid4().hex[:7],16)+1000000000)
 for c in [admin,cash]:assert call(c,'backend/venta.php',dict(sku=sku,cantidad=1,fecha='2099-12-29',usuario_id=1))[0]==201
 own=json.loads(call(cash,'backend/registros.php?fecha=2099-12-29&usuario_id=1')[1])
 allrows=json.loads(call(admin,'backend/registros.php?fecha=2099-12-29')[1])
 assert len([r for r in own if r['sku']==sku])==1
 assert len([r for r in allrows if r['sku']==sku])==2
 # A changed role takes effect even for an existing session.
 sql("UPDATE usuarios SET rol='Administrador' WHERE username='"+name+"'")
 assert call(cash,'Articulos/alta.php')[0]==200
 sql("UPDATE usuarios SET rol='Cajero' WHERE username='"+name+"'")
 assert call(cash,'Articulos/alta.php')[0]==403
 call(cash,'backend/logout.php')
 assert call(cash,'backend/consulta.php')[0]==401
 print('PASS: roles, direct URL/API restrictions, spoofed role/author ignored, own records, role refresh, logout')
finally:
 if 'sku' in locals():sql("DELETE FROM registros WHERE sku='"+sku+"' AND fecha='2099-12-29'")
 sql("DELETE FROM usuarios WHERE username='"+name+"'")
