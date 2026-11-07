"""Entradas/mermas integration checks; disposable products, users and movements."""
import urllib.request as u, urllib.parse as p, urllib.error as e, http.cookiejar, json, re, subprocess, uuid
from concurrent.futures import ThreadPoolExecutor
from datetime import datetime
from zoneinfo import ZoneInfo
base='http://127.0.0.1:8080/'
def client(): return u.build_opener(u.HTTPCookieProcessor(http.cookiejar.CookieJar()))
def call(c,path,data=None):
 req=u.Request(base+path,data=p.urlencode(data,doseq=True).encode() if data is not None else None)
 try:
  r=c.open(req);return r.status,r.read().decode()
 except e.HTTPError as err:return err.code,err.read().decode()
def sql(q):return subprocess.check_output(['docker','compose','-f','/workspace/inventarios-env/compose.yaml','exec','-T','db','mysql','-uroot','punto_de_venta','-N','-e',q],text=True).strip()
a=client();cash=client();seed=1600000000+int(uuid.uuid4().hex[:6],16);skus=[str(seed),str(seed+1)];ids=[];name='test_'+uuid.uuid4().hex[:16]
assert call(a,'backend/login.php',dict(username='Admin',password='Admin123'))[0]==200
csrf=re.search('name="csrf" value="([^"]+)"',call(a,'Articulos/alta.php')[1])[1]
def create(i,category,stock,mode):
 data=dict(csrf=csrf,id=0,sku=skus[i],nombre='Movimiento test '+str(i),categoria=category,unidad='Tallo' if i==0 else 'Pieza',stock=stock,stock_minimo=0,precio=1,observaciones='',modalidad=mode)
 assert 'Producto guardado correctamente' in call(a,'Articulos/alta.php',data)[1]
 pid=sql('SELECT id_articulo FROM articulo WHERE sku='+skus[i]);ids.append(pid);return pid
def stock(pid):return int(sql('SELECT stock FROM articulo WHERE id_articulo='+pid))
def move(kind,pid,qty,reason,detail='',token=None):
 data=dict(csrf=csrf,producto_id=pid,cantidad=qty,motivo=reason,detalle=detail,operacion_id=token or uuid.uuid4().hex)
 return call(a,'Articulos/movimiento.php?tipo='+kind,data)
try:
 material=create(0,'Flores',10,'Individual');arr=create(1,'Arreglos',0,'Ambas')
 token=uuid.uuid4().hex
 assert 'Entrada registrada' in move('entrada',material,50,'Compra','Proveedor de prueba',token)[1]
 assert stock(material)==60
 assert 'Entrada registrada' in move('entrada',material,50,'Compra','Proveedor de prueba',token)[1] and stock(material)==60
 assert move('entrada',material,51,'Compra','Proveedor de prueba',token)[0]==422 and stock(material)==60
 loss_token=uuid.uuid4().hex
 assert 'Merma registrada' in move('merma',material,5,'Marchitez','Flores dañadas',loss_token)[1]
 assert 'Merma registrada' in move('merma',material,5,'Marchitez','Flores dañadas',loss_token)[1] and stock(material)==55
 for qty in [0,-1,'1.5',56]: assert move('merma',material,qty,'Daño')[0]==422
 assert stock(material)==55
 assert move('entrada',material,2,'Otro')[0]==422
 assert move('entrada',material,2,'Motivo inventado')[0]==422
 assert move('entrada',arr,1,'Compra')[0]==422 and stock(arr)==0
 assert call(a,'Articulos/movimiento.php?tipo=entrada',dict(csrf='incorrecto'))[0]==403
 # Existing stock cannot be overwritten through the editor.
 assert 'Las existencias cambiaron' in call(a,'Articulos/consulta.php',dict(csrf=csrf,id=material,sku=skus[0],nombre='Movimiento test 0',categoria='Flores',unidad='Tallo',stock=999,stock_minimo=0,precio=1,observaciones='',modalidad='Individual'))[1]
 assert stock(material)==55
 assert 'Componentes guardados' in call(a,'Articulos/componentes.php?id='+arr,dict(csrf=csrf,accion='receta',**{'material[]':[material],'cantidad[]':[2]}))[1]
 assert 'Arreglos preparados' in call(a,'Articulos/componentes.php?id='+arr,dict(csrf=csrf,accion='preparar',unidades=1,operacion_id=uuid.uuid4().hex))[1]
 assert stock(material)==53 and stock(arr)==1
 assert 'Merma registrada' in move('merma',arr,1,'Daño')[1]
 assert stock(material)==53 and stock(arr)==0, 'Merma de arreglo devolvió materiales'
 date=datetime.now(ZoneInfo('America/Mexico_City')).date().isoformat()
 records=json.loads(call(a,'backend/registros.php?fecha='+date)[1])
 loss=next(r for r in records if r['operacion_id']==loss_token)
 assert loss['tipo']=='Merma' and loss['motivo']=='Marchitez: Flores dañadas' and loss['responsable']=='Admin'
 assert int(loss['movimientos'][0]['cantidad'])==-5 and int(loss['movimientos'][0]['stock_resultante'])==55
 # Only administrators can see or submit these operations.
 assert 'Usuario creado correctamente' in call(a,'usuarios.php',dict(csrf=csrf,username=name,password='Temporal123',rol='Cajero'))[1]
 assert call(cash,'backend/login.php',dict(username=name,password='Temporal123'))[0]==200
 for kind in ['entrada','merma']:
  assert call(cash,'Articulos/movimiento.php?tipo='+kind)[0]==403
  assert call(cash,'Articulos/movimiento.php?tipo='+kind,dict(csrf=csrf,producto_id=material,cantidad=1,motivo='Compra'))[0]==403
 assert 'Registrar entrada' not in call(cash,'Articulos/consulta.php')[1]
 assert 'Registrar merma' not in call(cash,'Articulos/consulta.php')[1]
 assert not any(r['operacion_id']==loss_token for r in json.loads(call(cash,'backend/registros.php?fecha='+date)[1]))
 # Overflow is atomic and cannot leave a half-recorded movement.
 sql('UPDATE articulo SET stock=2147483647 WHERE id_articulo='+material)
 overflow=uuid.uuid4().hex
 assert move('entrada',material,1,'Compra',token=overflow)[0]==422
 assert stock(material)==2147483647
 assert sql("SELECT COUNT(*) FROM registros WHERE operacion_id='"+overflow+"'")=='0'
 # Two independent database workers attempt to write off the last three units.
 sql('UPDATE articulo SET stock=3 WHERE id_articulo='+material)
 actor=int(sql("SELECT id FROM usuarios WHERE username='Admin'"))
 code="require '/app/backend/movimientos_stock.php'; $x=json_decode($argv[1],true); try { registerStockMovement(inventoryDb(),$x['actor'],'merma',$x['post']); echo 'OK'; } catch (DomainException $e) { echo 'REJECTED'; }"
 def race(_):
  args=dict(actor=actor,post=dict(producto_id=material,cantidad=3,motivo='Daño',detalle='',operacion_id=uuid.uuid4().hex))
  return subprocess.check_output(['docker','compose','-f','/workspace/inventarios-env/compose.yaml','exec','-T','web','php','-d','pdo_mysql.default_socket=/var/run/mysqld/mysqld.sock','-r',code,json.dumps(args)],text=True)
 with ThreadPoolExecutor(2) as pool: responses=list(pool.map(race,range(2)))
 assert sorted(responses)==['OK','REJECTED'] and stock(material)==0
 print('PASS: entradas, mermas, motivos, historial, deduplicación, permisos, límites y mermas simultáneas sin stock negativo')
finally:
 if ids:sql('DELETE FROM componentes WHERE arreglo_id IN ('+','.join(ids)+')')
 sql('DELETE FROM registros WHERE sku IN ('+','.join(skus)+')')
 sql('DELETE FROM articulo WHERE sku IN ('+','.join(skus)+')')
 sql("DELETE FROM usuarios WHERE username='"+name+"'")
