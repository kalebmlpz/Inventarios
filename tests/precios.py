"""Historical prices, exact cents, corrections and monetary reporting checks."""
import urllib.request as u, urllib.parse as p, urllib.error as e, http.cookiejar, json, re, subprocess, uuid, csv, io
from decimal import Decimal
base='http://127.0.0.1:8080/'
def client():return u.build_opener(u.HTTPCookieProcessor(http.cookiejar.CookieJar()))
def call(c,path,data=None):
 req=u.Request(base+path,data=p.urlencode(data).encode() if data is not None else None)
 try:
  r=c.open(req);return r.status,r.read().decode('utf-8-sig'),r.headers
 except e.HTTPError as err:return err.code,err.read().decode('utf-8-sig'),err.headers

def sql(q):return subprocess.check_output(['docker','compose','-f','/workspace/inventarios-env/compose.yaml','exec','-T','db','mysql','-uroot','punto_de_venta','-N','-e',q],text=True).strip()
a=client();cash=client();seed=1100000000+int(uuid.uuid4().hex[:6],16);skus=[str(seed+i) for i in range(3)];ids=[];name='test_'+uuid.uuid4().hex[:12]
assert call(a,'backend/login.php',dict(username='Admin',password='Admin123'))[0]==200
csrf=re.search('name="csrf" value="([^"]+)"',call(a,'usuarios.php')[1])[1]
def create(i,price,stock):
 assert 'Producto guardado correctamente' in call(a,'Articulos/alta.php',dict(csrf=csrf,id=0,sku=skus[i],nombre='Precio test '+str(i),categoria='Flores',unidad='Tallo',stock=stock,stock_minimo=0,precio=price,observaciones='',modalidad='Individual'))[1]
 pid=sql('SELECT id_articulo FROM articulo WHERE sku='+skus[i]);ids.append(pid);return pid

def sale(i,qty,token=None,quote=None,**extra):
 data=dict(csrf=csrf,operacion_id=token or uuid.uuid4().hex,sku=skus[i],cantidad=qty,fecha='2099-08-15',modo='Preparado')
 if quote is not None:data['precio_confirmado']=quote
 data.update(extra)
 status,body,_=call(a,'backend/venta.php',data)
 return status,json.loads(body),data['operacion_id']

def receipt(token):return json.loads(sql("SELECT JSON_OBJECT('id',id,'price',precio_unitario,'amount',importe,'currency',moneda) FROM registros WHERE operacion_id='"+token+"'"))

def correction(sid,qty):
 token=uuid.uuid4().hex
 assert 'Corrección registrada' in call(a,'Registros/devolucion.php?id='+str(sid),dict(csrf=csrf,cantidad=qty,tipo='Devolución',tratamiento='sin_stock',motivo='Precio original',confirmar='1',operacion_id=token))[1]
 return receipt(token)
try:
 product=create(0,'0.10',100);zero=create(1,'0.00',3);large=create(2,'99999999.99',2147483647)
 status,body,token=sale(0,3,quote='0.1',importe='0.01',precio_unitario='0.00',moneda='USD',producto_id=product)
 assert status==201 and body['precio_unitario']=='0.10' and body['importe']=='0.30' and body['moneda']=='MXN'
 original=receipt(token)
 sql("UPDATE articulo SET precio='0.20' WHERE id_articulo="+product)
 status,body,_=sale(0,3,token,quote='0.10')
 assert status==201 and body['importe']=='0.30'
 assert sql('SELECT stock FROM articulo WHERE id_articulo='+product)=='97'
 rejected=uuid.uuid4().hex
 assert sale(0,1,rejected,quote='0.10')[0]==409
 assert sql("SELECT COUNT(*) FROM registros WHERE operacion_id='"+rejected+"'")=='0'
 assert sale(0,1,quote='0.20',producto_id=999999999)[0]==409
 assert sale(0,1,quote=['0.20'])[0]==409
 refund=correction(original['id'],1)
 assert Decimal(str(refund['price']))==Decimal('0.10') and Decimal(str(refund['amount']))==Decimal('0.10') and refund['currency']=='MXN'
 status,body,new=sale(0,2,quote='0.20');assert status==201 and body['importe']=='0.40'
 assert sale(0,999,quote='0.20')[0]==409
 status,body,free=sale(1,1,quote='0.00');assert status==201 and body['importe']=='0.00'
 oldstatus,oldbody,old=sale(0,1,quote='0.20');oldreceipt=receipt(old)
 sql('UPDATE registros SET precio_unitario=NULL,importe=NULL,moneda=NULL WHERE id='+str(oldreceipt['id']))
 unknown_refund=correction(oldreceipt['id'],1)
 assert unknown_refund['price'] is None and unknown_refund['amount'] is None and unknown_refund['currency'] is None
 status,body,huge=sale(2,2147483647,quote='99999999.99')
 expected=Decimal('99999999.99')*Decimal(2147483647)
 assert status==201 and Decimal(body['importe'])==expected,body
 assert sql('SELECT stock FROM articulo WHERE id_articulo='+large)=='0'
 # A second stored currency must not be added to the MXN total.
 sql("UPDATE registros SET moneda='USD' WHERE operacion_id='"+free+"'")
 query='Reportes/reportes.php?'+p.urlencode(dict(reporte='importes',desde='2099-08-15',hasta='2099-08-15',exportar='csv'))
 status,body,headers=call(a,query);assert status==200 and 'text/csv' in headers['Content-Type']
 rows=list(csv.reader(io.StringIO(body),delimiter=';'))
 assert len(rows[0])==12
 by_id={r[0]:r for r in rows[1:]}
 assert by_id[str(original['id'])][8:]==['0.10','0.30','0.10','0.20']
 assert by_id[str(oldreceipt['id'])][8:]==['Sin precio registrado','','','']
 assert Decimal(by_id[str(receipt(huge)['id'])][9])==expected
 code="require '/app/backend/reportes.php'; $r=loadReport(inventoryDb(),'importes','2099-08-15','2099-08-15',$s); echo json_encode($s);"
 summary=json.loads(subprocess.check_output(['docker','compose','-f','/workspace/inventarios-env/compose.yaml','exec','-T','web','php','-d','pdo_mysql.default_socket=/var/run/mysqld/mysqld.sock','-r',code],text=True))
 assert summary['sin_precio']==1
 totals={r['moneda']:r for r in summary['totales']}
 assert Decimal(totals['MXN']['bruto'])==expected+Decimal('0.70')
 assert Decimal(totals['MXN']['corregido'])==Decimal('0.10')
 assert Decimal(totals['MXN']['neto'])==expected+Decimal('0.60')
 assert Decimal(totals['USD']['neto'])==0
 assert 'Usuario creado correctamente' in call(a,'usuarios.php',dict(csrf=csrf,username=name,rol='Cajero',password='Temporal123'))[1]
 assert call(cash,'backend/login.php',dict(username=name,password='Temporal123'))[0]==200
 assert call(cash,query)[0]==403
 print('PASS: precios históricos, centavos exactos, cambios de precio, correcciones, reenvíos, totales grandes, ventas antiguas, monedas separadas y permisos')
finally:
 sql('DELETE FROM registros WHERE sku IN ('+','.join(skus)+')')
 sql('DELETE FROM articulo WHERE sku IN ('+','.join(skus)+')')
 sql("DELETE FROM usuarios WHERE username='"+name+"'")
