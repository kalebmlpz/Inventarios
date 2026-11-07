"""Report range, aggregation, permission and CSV checks using disposable data."""
import urllib.request as u, urllib.parse as p, urllib.error as e, http.cookiejar, json, re, subprocess, uuid, csv, io
from datetime import datetime
from zoneinfo import ZoneInfo
base='http://127.0.0.1:8080/'
def client():return u.build_opener(u.HTTPCookieProcessor(http.cookiejar.CookieJar()))
def call(c,path,data=None):
 req=u.Request(base+path,data=p.urlencode(data,doseq=True).encode() if data is not None else None)
 try:
  r=c.open(req);return r.status,r.read().decode('utf-8-sig'),r.headers,r.url
 except e.HTTPError as err:return err.code,err.read().decode('utf-8-sig'),err.headers,err.url

def sql(q):return subprocess.check_output(['docker','compose','-f','/workspace/inventarios-env/compose.yaml','exec','-T','db','mysql','-uroot','punto_de_venta','-N','-e',q],text=True).strip()
a=client();cash=client();anon=client();seed=1200000000+int(uuid.uuid4().hex[:6],16);skus=[str(seed+i) for i in range(4)];ids=[];name='test_'+uuid.uuid4().hex[:12]
assert call(a,'backend/login.php',dict(username='Admin',password='Admin123'))[0]==200
csrf=re.search('name="csrf" value="([^"]+)"',call(a,'Articulos/alta.php')[1])[1]
today=datetime.now(ZoneInfo('America/Mexico_City')).date().isoformat()
def create(i,title,category,unit,stock,mode='Individual'):
 data=dict(csrf=csrf,id=0,sku=skus[i],nombre=title,categoria=category,unidad=unit,stock=stock,stock_minimo=0,precio=1,observaciones='',modalidad=mode)
 assert 'Producto guardado correctamente' in call(a,'Articulos/alta.php',data)[1]
 pid=sql('SELECT id_articulo FROM articulo WHERE sku='+skus[i]);ids.append(pid);return pid

def sale(i,quantity,mode='Preparado'):
 token=uuid.uuid4().hex
 assert call(a,'backend/venta.php',dict(csrf=csrf,operacion_id=token,sku=skus[i],cantidad=quantity,fecha='2099-04-10',modo=mode))[0]==201
 return sql("SELECT id FROM registros WHERE operacion_id='"+token+"'")

def report(kind,start,end):
 status,content,headers,_=call(a,'Reportes/reportes.php?'+p.urlencode(dict(reporte=kind,desde=start,hasta=end,exportar='csv')))
 assert status==200 and headers['Content-Type'].startswith('text/csv')
 assert 'attachment' in headers['Content-Disposition']
 return list(csv.reader(io.StringIO(content),delimiter=';'))
try:
 rose=create(0,'Flor reporte','Flores','Tallo',100);vase=create(1,'Base reporte','Bases','Pieza',20);arr=create(2,'Arreglo reporte','Arreglos','Pieza',0,'Ambas');formula=create(3,'=SUM(1,2)','Accesorios','Pieza',5)
 assert 'Componentes guardados' in call(a,'Articulos/componentes.php?id='+arr,dict(csrf=csrf,accion='receta',**{'material[]':[rose,vase],'cantidad[]':[3,1]}))[1]
 assert 'Arreglos preparados' in call(a,'Articulos/componentes.php?id='+arr,dict(csrf=csrf,accion='preparar',unidades=2,operacion_id=uuid.uuid4().hex))[1]
 ready=sale(2,2);order=sale(2,2,'Por encargo');sale(0,2);sale(3,1)
 # One product sold in two different historical units must stay in separate rows.
 sql("UPDATE articulo SET unidad='Ramo' WHERE id_articulo="+rose);sale(0,1);sql("UPDATE articulo SET unidad='Tallo' WHERE id_articulo="+rose)
 for sid,treatment,kind in [(ready,'producto','Devolución'),(order,'captura','Cancelación')]:
  assert 'Corrección registrada' in call(a,'Registros/devolucion.php?id='+sid,dict(csrf=csrf,cantidad=1,tipo=kind,tratamiento=treatment,motivo='Reporte de prueba',confirmar='1',operacion_id=uuid.uuid4().hex))[1]
 assert 'Merma registrada' in call(a,'Articulos/movimiento.php?tipo=merma',dict(csrf=csrf,producto_id=rose,cantidad=5,motivo='Marchitez',detalle='Reporte de prueba',operacion_id=uuid.uuid4().hex))[1]
 before=sql('SELECT COUNT(*) FROM registros')
 rows=report('ventas','2099-04-10','2099-04-10')
 actual=[r for r in rows[1:] if r[0]==skus[2]];assert len(actual)==1 and actual[0][3:]==['4','2','2'],actual
 # Corrections outside the requested sale date are included in its current net state.
 rose_rows=[r for r in rows[1:] if r[0]==skus[0]];assert len(rose_rows)==2
 assert next(r for r in rose_rows if r[2]=='Tallo')[3:] == ['2','0','2']
 assert next(r for r in rose_rows if r[2]=='Ramo')[3:] == ['1','0','1']
 assert next(r for r in rows[1:] if r[0]==skus[3])[1]=="'=SUM(1,2)", 'CSV formula was not neutralized'
 rows=report('consumo',today,today)
 assert next(r for r in rows[1:] if r[0]==skus[0])[3:]==['6','3','3']
 assert next(r for r in rows[1:] if r[0]==skus[1])[3:]==['2','1','1']
 rows=report('consumo','2099-04-10','2099-04-10')
 assert next(r for r in rows[1:] if r[0]==skus[0])[3:]==['6','0','6']
 rows=report('mermas',today,today)
 assert next(r for r in rows[1:] if r[0]==skus[0])[2:]==['Tallo','5','1']
 rows=report('movimientos','2099-04-10','2099-04-10')
 assert len([r for r in rows[1:] if r[3] in skus])==5
 assert all(r[1]=='2099-04-10' and r[2]=='Venta' for r in rows[1:] if r[3] in skus)
 assert len(report('mermas','2050-01-01','2050-01-02'))==1
 for query in [dict(reporte='ventas',desde='2026-02-30',hasta='2026-03-01'),dict(reporte='ventas',desde='2026-03-01',hasta='2026-02-01'),dict(reporte='invalid',desde=today,hasta=today)]:
  status,content,headers,_=call(a,'Reportes/reportes.php?'+p.urlencode(dict(query,exportar='csv')))
  assert status==400 and not headers.get('Content-Disposition')
 assert sql('SELECT COUNT(*) FROM registros')==before, 'Reading reports modified records'
 assert 'Reportes' in call(a,'menu.php')[1]
 assert 'Usuario creado correctamente' in call(a,'usuarios.php',dict(csrf=csrf,username=name,password='Temporal123',rol='Cajero'))[1]
 assert call(cash,'backend/login.php',dict(username=name,password='Temporal123'))[0]==200
 for path in ['Reportes/reportes.php','Reportes/reportes.php?exportar=csv']:
  assert call(cash,path)[0]==403
  assert call(anon,path)[3].endswith('/login.html')
 assert 'Reportes' not in call(cash,'menu.php')[1]
 print('PASS: reportes por periodo, netos corregidos, unidades separadas, consumo, mermas, CSV seguro, fechas y permisos')
finally:
 if ids:sql('DELETE FROM componentes WHERE arreglo_id IN ('+','.join(ids)+')')
 sql('DELETE FROM registros WHERE sku IN ('+','.join(skus)+')')
 sql('DELETE FROM articulo WHERE sku IN ('+','.join(skus)+')')
 sql("DELETE FROM usuarios WHERE username='"+name+"'")
