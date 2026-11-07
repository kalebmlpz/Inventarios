"""Read-only catalog filter checks using temporary products and a cashier account."""
import urllib.request as u, urllib.parse as p, urllib.error as e, http.cookiejar, re, subprocess, uuid
base='http://127.0.0.1:8080/'
def client():return u.build_opener(u.HTTPCookieProcessor(http.cookiejar.CookieJar()))
def call(c,path,data=None):
 req=u.Request(base+path,data=p.urlencode(data).encode() if data is not None else None)
 try:
  r=c.open(req);return r.status,r.read().decode()
 except e.HTTPError as err:return err.code,err.read().decode()
def sql(q):return subprocess.check_output(['docker','compose','-f','/workspace/inventarios-env/compose.yaml','exec','-T','db','mysql','-uroot','punto_de_venta','-N','-e',q],text=True)
def codes(html):
 body=re.search(r'<tbody>(.*?)</tbody>',html,re.S)[1]
 return re.findall(r'<tr><td>(\d+)</td>',body)
a=client();cash=client();uid=uuid.uuid4().hex[:12];term='Filtro '+uid;name='test_'+uid;seed=1400000000+int(uid[:6],16)
skus=[str(seed+i) for i in range(6)]
fixtures=[('Flores',0,0,'Individual'),('Flores',1,1,'Individual'),('Flores',2,1,'Individual'),('Bases',5,7,'Individual'),('Arreglos',0,0,'Por encargo'),('Arreglos',0,0,'Preparado')]
assert call(a,'backend/login.php',dict(username='Admin',password='Admin123'))[0]==200
csrf=re.search('name="csrf" value="([^"]+)"',call(a,'Articulos/alta.php')[1])[1]
try:
 for i,(category,stock,minimum,mode) in enumerate(fixtures):
  data=dict(csrf=csrf,id=0,sku=skus[i],nombre=term+' '+str(i),categoria=category,unidad='Pieza',stock=stock,stock_minimo=minimum,precio=1,observaciones='',modalidad=mode)
  result=call(a,'Articulos/alta.php',data)
  assert 'Producto guardado correctamente' in result[1],result
 assert 'Usuario creado correctamente' in call(a,'usuarios.php',dict(csrf=csrf,username=name,password='Temporal123',rol='Cajero'))[1]
 assert call(cash,'backend/login.php',dict(username=name,password='Temporal123'))[0]==200
 for c in [a,cash]:
  def filtered(**filters):
   code,html=call(c,'Articulos/consulta.php?'+p.urlencode(dict(q=term,**filters)));assert code==200
   return html
  assert set(codes(filtered()))==set(skus)
  assert set(codes(filtered(estado='bajo')))=={skus[i] for i in [0,1,3,5]}
  assert set(codes(filtered(estado='agotado')))=={skus[0],skus[5]}
  assert codes(filtered(estado='bajo',categoria='Bases'))==[skus[3]]
  assert codes(filtered(estado='agotado',categoria='Bases'))==[]
  assert set(codes(filtered(categoria='Arreglos')))=={skus[4],skus[5]}
  assert set(codes(filtered(estado='no valido',categoria="' OR 1=1 --")))==set(skus)
  assert 'Stock bajo</small>' in filtered(estado='bajo')
  assert 'Agotado</small>' in filtered(estado='agotado')
  assert 'Sin arreglos listos</small>' in filtered(estado='agotado')
  assert skus[4] not in codes(filtered(estado='bajo')), 'Un arreglo solo por encargo no mantiene stock listo'
  assert '6 productos encontrados' in filtered()
 print('PASS: filtros por stock/categoría/búsqueda, límite exacto, stock cero, arreglos por encargo y ambos roles')
finally:
 sql('DELETE FROM registros WHERE sku IN ('+','.join(skus)+')')
 sql('DELETE FROM articulo WHERE sku IN ('+','.join(skus)+')')
 sql("DELETE FROM usuarios WHERE username='"+name+"'")
