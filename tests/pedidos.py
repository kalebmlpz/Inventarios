"""Pedidos: HTTP, permisos, transacciones y carreras reales en procesos PHP independientes.
Usa únicamente la base local de desarrollo; elimina sus datos temporales al terminar.
"""
import http.cookiejar
import json
import re
import subprocess
import urllib.error
import urllib.parse
import urllib.request
import uuid
from concurrent.futures import ThreadPoolExecutor

COMPOSE = ['docker', 'compose', '-f', '/workspace/inventarios-env/compose.yaml']
BASE = 'http://127.0.0.1:8080/'
PAGE = 'Pedidos/pedidos.php'

def sql(query):
    return subprocess.check_output(COMPOSE + ['exec', '-T', 'db', 'mysql', '-uroot', 'punto_de_venta', '-N', '-e', query], text=True).strip()

def client():
    return urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))

def call(c, path, data=None):
    request = urllib.request.Request(BASE + path, data=urllib.parse.urlencode(data, doseq=True).encode() if data is not None else None)
    try:
        response = c.open(request)
        return response.status, response.read().decode()
    except urllib.error.HTTPError as error:
        return error.code, error.read().decode()

def token(c):
    return re.search('name="csrf" value="([^"]+)"', call(c, PAGE)[1])[1]

admin, cashier, outsider = client(), client(), client()
assert call(admin, 'backend/login.php', dict(username='Admin', password='Admin123'))[0] == 200
csrf = token(admin)
actor = int(sql("SELECT id FROM usuarios WHERE username='Admin'"))
suffix = uuid.uuid4().hex[:12]
usernames = ['ped_' + suffix, 'otro_' + suffix]
products, orders = [], []
seed = 1700000000 + int(uuid.uuid4().hex[:6], 16)

def product(index, name, stock, category='Flores', mode='Individual'):
    result = call(admin, 'Articulos/alta.php', dict(csrf=csrf, id=0, sku=str(seed+index), nombre=name, categoria=category, unidad='Pieza', stock=stock, stock_minimo=0, precio='12.34', observaciones='', modalidad=mode))
    assert 'Producto guardado correctamente' in result[1], result
    pid = int(sql('SELECT id_articulo FROM articulo WHERE sku='+str(seed+index)))
    products.append(pid)
    return pid

def stock(pid):
    return int(sql('SELECT stock FROM articulo WHERE id_articulo='+str(pid)))

def new_order(c, ct, **overrides):
    data = dict(accion='crear', csrf=ct, cliente='Cliente <encargo>', telefono='+52 555 123 4567', articulo_id=str(arrangement), cantidad='2', fecha_entrega='2099-11-12T14:30', operacion_id=uuid.uuid4().hex)
    data.update(overrides)
    result = call(c, PAGE, data)
    if result[0] == 200:
        oid = sql("SELECT id FROM pedidos WHERE operacion_id='"+data['operacion_id']+"'")
        assert oid, result
        if oid not in orders:
            orders.append(oid)
    else:
        oid = None
    return oid, data, result

def transition(c, ct, oid, state, version=None, **overrides):
    if version is None:
        version = sql('SELECT version FROM pedidos WHERE id='+oid)
    data = dict(accion='estado', csrf=ct, pedido_id=oid, estado=state, version=version, operacion_id=uuid.uuid4().hex)
    data.update(overrides)
    return call(c, PAGE, data), data

def race(oid, state, version, op=None):
    code = "require '/app/backend/pedidos.php'; $x=json_decode($argv[1],true); try { transitionOrder(inventoryDb(),$x['user'],$x['post']); echo 'OK'; } catch (DomainException $e) { echo 'REJECTED'; }"
    payload = dict(user=dict(id=actor, rol='Administrador'), post=dict(pedido_id=oid, estado=state, version=version, operacion_id=op or uuid.uuid4().hex))
    return subprocess.check_output(COMPOSE + ['exec', '-T', 'web', 'php', '-d', 'pdo_mysql.default_socket=/var/run/mysqld/mysqld.sock', '-r', code, json.dumps(payload)], text=True)

try:
    for name, c in zip(usernames, [cashier, outsider]):
        assert 'Usuario creado correctamente' in call(admin, 'usuarios.php', dict(csrf=csrf, username=name, password='Temporal123', rol='Cajero'))[1]
        assert call(c, 'backend/login.php', dict(username=name, password='Temporal123'))[0] == 200
    cash_csrf, out_csrf = token(cashier), token(outsider)
    assert call(client(), PAGE)[1].find('Pedidos por encargo') == -1
    flower = product(0, 'Flor pedidos', 20)
    base = product(1, 'Base pedidos', 1, 'Bases')
    arrangement = product(2, 'Arreglo pedidos', 0, 'Arreglos', 'Ambas')
    recipe = dict(csrf=csrf, accion='receta', **{'material[]':[flower,base], 'cantidad[]':[3,1]})
    assert 'Componentes guardados' in call(admin, 'Articulos/componentes.php?id='+str(arrangement), recipe)[1]
    for invalid in [dict(cliente=''), dict(cliente='x'*101), dict(telefono='abc'), dict(cantidad='0'), dict(cantidad='2147483648'), dict(fecha_entrega='2099-02-30T10:00'), dict(fecha_entrega='2000-01-01T10:00'), dict(fecha_entrega='2099-11-12T14:30\x00'), dict(articulo_id=str(flower))]:
        assert new_order(cashier, cash_csrf, **invalid)[2][0] == 422, invalid
    assert new_order(cashier, 'bad')[2][0] == 403
    oid, creation, result = new_order(cashier, cash_csrf)
    assert result[0] == 200 and stock(flower) == 20 and stock(base) == 1
    assert '&lt;encargo&gt;' in result[1] and 'Cliente <encargo>' not in result[1]
    assert call(cashier, PAGE, creation)[0] == 200
    assert sql("SELECT COUNT(*) FROM pedidos WHERE operacion_id='"+creation['operacion_id']+"'") == '1'
    assert call(cashier, PAGE, dict(creation, cantidad='3'))[0] == 422
    assert 'Cliente &lt;encargo&gt;' not in call(outsider, PAGE)[1]
    assert transition(outsider, out_csrf, oid, 'Cancelado')[0][0] == 422
    assert transition(cashier, cash_csrf, oid, 'En preparación')[0][0] == 422
    assert transition(admin, csrf, oid, 'Entregado')[0][0] == 422
    assert transition(admin, 'bad', oid, 'En preparación')[0][0] == 403
    # First component is available; second is insufficient. All writes must roll back.
    assert transition(admin, csrf, oid, 'En preparación')[0][0] == 422
    assert stock(flower) == 20 and stock(base) == 1
    assert sql('SELECT estado,version,IFNULL(venta_id,0) FROM pedidos WHERE id='+oid) == 'Pendiente\t0\t0'
    assert sql('SELECT COUNT(*) FROM pedidos_eventos WHERE pedido_id='+oid) == '0'
    sql('UPDATE articulo SET stock=10 WHERE id_articulo='+str(base))
    sql("UPDATE articulo SET precio=99,nombre='Nombre cambiado' WHERE id_articulo="+str(arrangement))
    # Two independent workers race to prepare the same order.
    with ThreadPoolExecutor(2) as pool:
        results = list(pool.map(lambda _:race(oid, 'En preparación', 0), range(2)))
    assert sorted(results) == ['OK', 'REJECTED'], results
    assert (stock(flower),stock(base),stock(arrangement)) == (14,8,0)
    sale = sql('SELECT venta_id FROM pedidos WHERE id='+oid)
    assert sql('SELECT precio_unitario,importe,nombre_snapshot FROM registros WHERE id='+sale) == '12.34\t24.68\tArreglo pedidos'
    assert transition(admin, csrf, oid, 'Cancelado')[0][0] == 422
    assert transition(admin, csrf, oid, 'En preparación', version=0)[0][0] == 422
    assert transition(cashier, cash_csrf, oid, 'Listo')[0][0] == 422
    ready_result, ready = transition(admin, csrf, oid, 'Listo')
    assert ready_result[0] == 200
    assert transition(cashier, cash_csrf, oid, 'Entregado')[0][0] == 200
    assert call(admin, PAGE, ready)[0] == 200  # old exact receipt remains safe after delivery
    assert call(admin, PAGE, dict(ready, estado='Cancelado'))[0] == 422
    assert transition(admin, csrf, oid, 'Listo')[0][0] == 422
    assert (stock(flower),stock(base),stock(arrangement)) == (14,8,0)
    assert sql('SELECT COUNT(*) FROM registros WHERE id='+sale) == '1'
    assert 'Entregado' in call(cashier, PAGE+'?estado=Entregado')[1]
    assert call(admin, PAGE+'?estado=invalid')[0] == 400
    # Same operation token replayed simultaneously prepares only once.
    second, _, _ = new_order(admin, csrf, cantidad='1')
    same = uuid.uuid4().hex
    with ThreadPoolExecutor(2) as pool:
        results = list(pool.map(lambda _:race(second, 'En preparación', 0, same), range(2)))
    assert results == ['OK','OK'] and stock(flower) == 11, results
    # Correct the full sale using the existing flow; closing must not return stock again.
    sale2 = sql('SELECT venta_id FROM pedidos WHERE id='+second)
    correction = dict(csrf=csrf, tipo='Cancelación', cantidad='1', tratamiento='captura', motivo='Error antes de usar materiales', confirmar='1', operacion_id=uuid.uuid4().hex)
    assert call(admin, 'Registros/devolucion.php?id='+sale2, correction)[0] == 200
    assert stock(flower) == 14
    assert transition(admin, csrf, second, 'Listo')[0][0] == 422
    assert transition(admin, csrf, second, 'Cancelado')[0][0] == 200
    assert stock(flower) == 14
    pending, _, _ = new_order(cashier, cash_csrf, cantidad='1')
    assert transition(cashier, cash_csrf, pending, 'Cancelado')[0][0] == 200
    assert transition(admin, csrf, pending, 'En preparación')[0][0] == 422
    assert stock(flower) == 14
    # Two different orders compete for the last materials.
    third, _, _ = new_order(admin, csrf, cantidad='1')
    fourth, _, _ = new_order(admin, csrf, cantidad='1')
    sql('UPDATE articulo SET stock=3 WHERE id_articulo='+str(flower))
    with ThreadPoolExecutor(2) as pool:
        results = list(pool.map(lambda x:race(x,'En preparación',0), [third,fourth]))
    assert sorted(results) == ['OK','REJECTED'] and stock(flower) == 0, results
    print('PASS: pedidos, validación, privacidad, CSRF, estados, precio acordado, consumo único, rollback, reenvíos, correcciones y concurrencia entre pedidos')
finally:
    if orders:
        ids = ','.join(orders)
        sql('DELETE FROM pedidos_eventos WHERE pedido_id IN ('+ids+'); DELETE FROM pedidos WHERE id IN ('+ids+')')
    if products:
        ids = ','.join(map(str,products))
        sql('DELETE FROM componentes WHERE arreglo_id IN ('+ids+'); DELETE FROM registros WHERE articulo_id IN ('+ids+'); DELETE FROM articulo WHERE id_articulo IN ('+ids+')')
    sql("DELETE FROM usuarios WHERE username IN ('"+"','".join(usernames)+"')")
