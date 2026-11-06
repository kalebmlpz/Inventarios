"""Functional inventory checks. Requires the local development DB and PHP server."""
import urllib.request as u, urllib.parse as p, urllib.error as e, http.cookiejar, json, re, subprocess, uuid
from concurrent.futures import ThreadPoolExecutor
base='http://127.0.0.1:8080/'
def client():return u.build_opener(u.HTTPCookieProcessor(http.cookiejar.CookieJar()))
def call(c,path,data=None):
 req=u.Request(base+path,data=p.urlencode(data,doseq=True).encode() if data is not None else None)
 try:
  r=c.open(req);return r.status,r.read().decode()
 except e.HTTPError as err:return err.code,err.read().decode()
def sql(q):return subprocess.check_output(['docker','compose','-f','/workspace/inventarios-env/compose.yaml','exec','-T','db','mysql','-uroot','punto_de_venta','-N','-e',q],text=True).strip()
a=client();other=client()
for c in [a,other]:assert call(c,'backend/login.php',dict(username='Admin',password='Admin123'))[0]==200
csrf=re.search('name="csrf" value="([^"]+)"',call(a,'Articulos/alta.php')[1])[1]
csrf2=re.search('name="csrf" value="([^"]+)"',call(other,'Articulos/alta.php')[1])[1]
seed=1500000000+int(uuid.uuid4().hex[:6],16)
skus=[str(seed+i) for i in range(4)];ids=[]
def product(i,name,stock,cat='Flores',mode='Individual'):
 data=dict(csrf=csrf,id=0,sku=skus[i],nombre=name,categoria=cat,unidad='Pieza',stock=stock,stock_minimo=0,precio=1,observaciones='',modalidad=mode)
 result=call(a,'Articulos/alta.php',data)
 assert 'Producto guardado correctamente' in result[1],result
 pid=sql('SELECT id_articulo FROM articulo WHERE sku='+skus[i]);ids.append(pid);return pid
def stock(pid):return int(sql('SELECT stock FROM articulo WHERE id_articulo='+pid))
def sale(i,qty,mode='Preparado',token=None,c=a,csrf_token=csrf):
 return call(c,'backend/venta.php',dict(csrf=csrf_token,operacion_id=token or uuid.uuid4().hex,sku=skus[i],cantidad=qty,fecha='2099-12-27',modo=mode))
try:
 rose=product(0,'Flor test',30);vase=product(1,'Base test',5,'Bases');arr=product(2,'Arreglo test',0,'Arreglos','Ambas');single=product(3,'Unidad test',1)
 recipe=dict(csrf=csrf,accion='receta',**{'material[]':[rose,vase],'cantidad[]':[3,1]})
 assert 'Componentes guardados' in call(a,'Articulos/componentes.php?id='+arr,recipe)[1]
 prep=dict(csrf=csrf,accion='preparar',unidades=2,operacion_id=uuid.uuid4().hex)
 assert 'Arreglos preparados' in call(a,'Articulos/componentes.php?id='+arr,prep)[1]
 assert (stock(rose),stock(vase),stock(arr))==(24,3,2)
 # Repeating the exact request does not consume twice.
 assert 'Arreglos preparados' in call(a,'Articulos/componentes.php?id='+arr,prep)[1]
 assert stock(rose)==24
 assert sale(2,1)[0]==201
 assert (stock(rose),stock(vase),stock(arr))==(24,3,1)
 token=uuid.uuid4().hex
 assert sale(2,2,'Por encargo',token)[0]==201
 assert sale(2,2,'Por encargo',token)[0]==201
 assert (stock(rose),stock(vase),stock(arr))==(18,1,1)
 assert sale(2,3,'Por encargo')[0]==409
 assert (stock(rose),stock(vase),stock(arr))==(18,1,1), 'Failed recipe consumed some materials'
 assert sale(2,2)[0]==409
 assert sale(2,0)[0]==400
 assert sale(0,1,'Por encargo')[0]==409
 assert call(a,'backend/eliminar_producto.php',dict(csrf=csrf,id=rose))[0]==409
 bad=dict(recipe);bad['material[]']=[arr];bad['cantidad[]']=[1]
 assert 'no otros arreglos' in call(a,'Articulos/componentes.php?id='+arr,bad)[1]
 assert int(sql('SELECT COUNT(*) FROM componentes WHERE arreglo_id='+arr))==2
 records=json.loads(call(a,'backend/registros.php?fecha=2099-12-27')[1])
 mine=[r for r in records if r['sku']==skus[2]]
 assert len(mine)==2 and sum(len(r['movimientos']) for r in mine)==3
 assert all(r['nombre']=='Arreglo test' and r['responsable']=='Admin' for r in mine)
 # Parallel independent PHP workers, different sessions, same last item.
 sid_a=next(cookie.value for handler in a.handlers if hasattr(handler,'cookiejar') for cookie in handler.cookiejar if cookie.name=='PHPSESSID')
 sid_b=next(cookie.value for handler in other.handlers if hasattr(handler,'cookiejar') for cookie in handler.cookiejar if cookie.name=='PHPSESSID')
 code="$x=json_decode($argv[1],true); session_id($x['sid']); $_SERVER['REQUEST_METHOD']='POST'; $_POST=$x['post']; include '/app/backend/venta.php'; echo '|'.http_response_code();"
 def race(args):
  sid,ct=args
  data=dict(sid=sid,post=dict(csrf=ct,operacion_id=uuid.uuid4().hex,sku=skus[3],cantidad='1',fecha='2099-12-27',modo='Preparado'))
  return subprocess.check_output(['docker','compose','-f','/workspace/inventarios-env/compose.yaml','exec','-T','web','php','-d','pdo_mysql.default_socket=/var/run/mysqld/mysqld.sock','-r',code,json.dumps(data)],text=True)
 with ThreadPoolExecutor(2) as pool:responses=list(pool.map(race,[(sid_a,csrf),(sid_b,csrf2)]))
 assert sum('|201' in r for r in responses)==1 and sum('|409' in r for r in responses)==1,responses
 assert stock(single)==0
 assert 'Producto eliminado del catálogo' in call(a,'backend/eliminar_producto.php',dict(csrf=csrf,id=single))[1]
 records=json.loads(call(a,'backend/registros.php?fecha=2099-12-27')[1])
 assert any(r['sku']==skus[3] and r['nombre']=='Unidad test' for r in records)
 print('PASS: recipes, preparation, prepared/custom sales, rollback, retry deduplication, history and concurrent last-unit sales')
finally:
 if ids:
  sql('DELETE FROM componentes WHERE arreglo_id IN ('+','.join(ids)+')')
 sql("DELETE FROM registros WHERE sku IN ('"+"','".join(skus)+"')")
 sql('DELETE FROM articulo WHERE sku IN ('+','.join(skus)+')')
