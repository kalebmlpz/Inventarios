"""Full/partial correction checks with disposable inventory and users."""
import urllib.request as u, urllib.parse as p, urllib.error as e, http.cookiejar, json, re, subprocess, uuid
from concurrent.futures import ThreadPoolExecutor
from datetime import datetime
from zoneinfo import ZoneInfo
base='http://127.0.0.1:8080/'
def client():return u.build_opener(u.HTTPCookieProcessor(http.cookiejar.CookieJar()))
def call(c,path,data=None):
 req=u.Request(base+path,data=p.urlencode(data,doseq=True).encode() if data is not None else None)
 try:
  r=c.open(req);return r.status,r.read().decode()
 except e.HTTPError as err:return err.code,err.read().decode()
def sql(q):return subprocess.check_output(['docker','compose','-f','/workspace/inventarios-env/compose.yaml','exec','-T','db','mysql','-uroot','punto_de_venta','-N','-e',q],text=True).strip()
a=client();cash=client();seed=1300000000+int(uuid.uuid4().hex[:6],16);skus=[str(seed+i) for i in range(5)];ids=[];name='test_'+uuid.uuid4().hex[:12]
assert call(a,'backend/login.php',dict(username='Admin',password='Admin123'))[0]==200
csrf=re.search('name="csrf" value="([^"]+)"',call(a,'Articulos/alta.php')[1])[1]
def create(i,category,stock,mode='Individual'):
 data=dict(csrf=csrf,id=0,sku=skus[i],nombre='Corrección test '+str(i),categoria=category,unidad='Pieza',stock=stock,stock_minimo=0,precio=1,observaciones='',modalidad=mode)
 assert 'Producto guardado correctamente' in call(a,'Articulos/alta.php',data)[1]
 pid=sql('SELECT id_articulo FROM articulo WHERE sku='+skus[i]);ids.append(pid);return pid
def stock(pid):return int(sql('SELECT stock FROM articulo WHERE id_articulo='+pid))
def sale(i,qty,mode='Preparado'):
 token=uuid.uuid4().hex
 assert call(a,'backend/venta.php',dict(csrf=csrf,operacion_id=token,sku=skus[i],cantidad=qty,fecha='2099-12-26',modo=mode))[0]==201
 return sql("SELECT id FROM registros WHERE operacion_id='"+token+"'")
def correct(sid,qty,treatment='sin_stock',kind='Cancelación',token=None,reason='Prueba de corrección',confirm='1'):
 data=dict(csrf=csrf,operacion_id=token or uuid.uuid4().hex,cantidad=qty,tipo=kind,tratamiento=treatment,motivo=reason,confirmar=confirm)
 return call(a,'Registros/devolucion.php?id='+sid,data)
def count(sid):return int(sql('SELECT COALESCE(SUM(cantidad),0) FROM registros WHERE venta_origen_id='+sid))
try:
 rose=create(0,'Flores',100);vase=create(1,'Bases',20);arr=create(2,'Arreglos',0,'Ambas');single=create(3,'Accesorios',20);deleted=create(4,'Accesorios',1)
 assert 'Componentes guardados' in call(a,'Articulos/componentes.php?id='+arr,dict(csrf=csrf,accion='receta',**{'material[]':[rose,vase],'cantidad[]':[3,1]}))[1]
 assert 'Arreglos preparados' in call(a,'Articulos/componentes.php?id='+arr,dict(csrf=csrf,accion='preparar',unidades=2,operacion_id=uuid.uuid4().hex))[1]
 ready=sale(2,2);token=uuid.uuid4().hex
 assert 'Corrección registrada' in correct(ready,1,'producto','Devolución',token)[1]
 assert stock(arr)==1 and stock(rose)==94 and stock(vase)==18
 assert 'Corrección registrada' in correct(ready,1,'producto','Devolución',token)[1] and count(ready)==1
 assert correct(ready,2)[0]==422 and count(ready)==1
 assert 'Corrección registrada' in correct(ready,1)[1] and count(ready)==2 and stock(arr)==1
 assert correct(ready,1)[0]==422
 order=sale(2,2,'Por encargo');baseline=(stock(rose),stock(vase))
 # Changing the recipe does not change the consumption recorded by this sale.
 assert 'Componentes guardados' in call(a,'Articulos/componentes.php?id='+arr,dict(csrf=csrf,accion='receta',**{'material[]':[rose,vase],'cantidad[]':[5,1]}))[1]
 assert 'Corrección registrada' in correct(order,1,'captura')[1]
 assert (stock(rose),stock(vase))==(baseline[0]+3,baseline[1]+1)
 assert 'Corrección registrada' in correct(order,1,'producto','Devolución')[1]
 assert stock(arr)==2 and (stock(rose),stock(vase))==(baseline[0]+3,baseline[1]+1)
 normal=sale(3,5)
 for qty in [0,-1,'1.5',6]:assert correct(normal,qty)[0]==422
 assert correct(normal,1,confirm='')[0]==422
 assert correct(normal,1,reason='')[0]==422
 assert correct(normal,1,'captura','Devolución')[0]==422
 assert count(normal)==0
 before=stock(single);sql('UPDATE articulo SET stock=2147483647 WHERE id_articulo='+single)
 assert correct(normal,1,'producto','Devolución')[0]==422 and count(normal)==0
 sql('UPDATE articulo SET stock='+str(before)+' WHERE id_articulo='+single)
 assert call(a,'Registros/devolucion.php?id='+normal,dict(csrf='incorrecto'))[0]==403
 sql("UPDATE articulo SET unidad='Paquete' WHERE id_articulo="+single)
 assert correct(normal,1,'producto','Devolución')[0]==422 and count(normal)==0
 sql("UPDATE articulo SET unidad='Pieza' WHERE id_articulo="+single)
 assert 'Corrección registrada' in correct(normal,2,'producto','Devolución')[1] and stock(single)==17
 # Legacy IDs are not guessed from a matching SKU.
 legacy=sale(3,1);sql('UPDATE registros SET articulo_id=NULL WHERE id='+legacy)
 assert correct(legacy,1,'producto','Devolución')[0]==422
 assert 'Corrección registrada' in correct(legacy,1)[1]
 removed=sale(4,1);sql('DELETE FROM articulo WHERE id_articulo='+deleted)
 replacement=create(4,'Accesorios',0)
 assert correct(removed,1,'producto','Devolución')[0]==422 and stock(replacement)==0
 assert 'Corrección registrada' in correct(removed,1)[1]
 # Full rollback when one original component no longer exists.
 missing=sale(2,1,'Por encargo');before=stock(rose)
 sql('DELETE FROM componentes WHERE arreglo_id='+arr+' AND componente_id='+vase)
 sql('DELETE FROM articulo WHERE id_articulo='+vase)
 assert correct(missing,1,'captura')[0]==422 and stock(rose)==before and count(missing)==0
 assert 'Corrección registrada' in correct(missing,1)[1]
 # A cashier cannot correct even its own sale.
 assert 'Usuario creado correctamente' in call(a,'usuarios.php',dict(csrf=csrf,username=name,password='Temporal123',rol='Cajero'))[1]
 assert call(cash,'backend/login.php',dict(username=name,password='Temporal123'))[0]==200
 assert call(cash,'Registros/devolucion.php?id='+normal)[0]==403
 assert call(cash,'Registros/devolucion.php?id='+normal,dict(csrf=csrf,cantidad=1))[0]==403
 # Independent database workers cannot correct the same remaining unit twice.
 last=sale(3,1);before=stock(single);actor=int(sql("SELECT id FROM usuarios WHERE username='Admin'"))
 code="require '/app/backend/correcciones_ventas.php'; $x=json_decode($argv[1],true); try { correctSale(inventoryDb(),$x['actor'],$x['id'],$x['post']); echo 'OK'; } catch (DomainException $e) { echo 'REJECTED'; }"
 def race(_):
  data=dict(actor=actor,id=int(last),post=dict(cantidad=1,tipo='Devolución',tratamiento='producto',motivo='Prueba concurrente',confirmar='1',operacion_id=uuid.uuid4().hex))
  return subprocess.check_output(['docker','compose','-f','/workspace/inventarios-env/compose.yaml','exec','-T','web','php','-d','pdo_mysql.default_socket=/var/run/mysqld/mysqld.sock','-r',code,json.dumps(data)],text=True)
 with ThreadPoolExecutor(2) as pool:results=list(pool.map(race,range(2)))
 assert sorted(results)==['OK','REJECTED'] and count(last)==1 and stock(single)==before+1
 originals=json.loads(call(a,'backend/registros.php?fecha=2099-12-26')[1])
 record=next(r for r in originals if str(r['id'])==ready)
 assert int(record['cantidad'])==2 and int(record['cantidad_corregida'])==2
 today=datetime.now(ZoneInfo('America/Mexico_City')).date().isoformat()
 corrections=json.loads(call(a,'backend/registros.php?fecha='+today)[1])
 assert any(str(r['venta_origen_id'])==ready and r['tipo']=='Devolución' and r['responsable']=='Admin' for r in corrections)
 print('PASS: correcciones completas/parciales, devolución de productos, captura por error, historial original, reenvíos, límites, permisos, productos cambiados/eliminados y concurrencia')
finally:
 if ids:sql('DELETE FROM componentes WHERE arreglo_id IN ('+','.join(ids)+')')
 sql('DELETE FROM registros WHERE sku IN ('+','.join(skus)+')')
 sql('DELETE FROM articulo WHERE sku IN ('+','.join(skus)+')')
 sql("DELETE FROM usuarios WHERE username='"+name+"'")
