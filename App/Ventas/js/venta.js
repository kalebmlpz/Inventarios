const form = document.getElementById('formulario-articulos')
const selection = document.getElementById('seleccion-producto')
const card = document.getElementById('articulos')
const finish = document.getElementById('finalizar')
const status = document.getElementById('estado-venta')
const mode = document.getElementById('modo')
const money = window.FloreriaMoney
const totalLabel = document.getElementById('total-pedido')
let updatingPrice = false
const storageKey = 'floreria-pedido-' + document.getElementById('usuario').value
let pending = []
try { pending = JSON.parse(sessionStorage.getItem(storageKey) || '[]'); if (!Array.isArray(pending)) pending = [] } catch { pending = [] }
pending = pending.filter(item => item && typeof item === 'object' && typeof item.sku === 'string' && typeof item.nombre === 'string' && Number.isSafeInteger(item.cantidad) && item.cantidad > 0 && item.cantidad <= 2147483647 && typeof item.operacion_id === 'string' && ['Preparado','Por encargo'].includes(item.modo))
let selected = null
let busy = false
let searchVersion = 0
function unitLabel(quantity, unit) {
    const names = {Pieza:['pieza','piezas'],Tallo:['tallo','tallos'],Ramo:['ramo','ramos'],Paquete:['paquete','paquetes']}
    return names[unit] ? names[unit][Number(quantity) === 1 ? 0 : 1] : unit
}
function hasPrice(item) { return Number.isInteger(item.producto_id) && item.producto_id > 0 && typeof item.precio_confirmado === 'string' && money.cents(item.precio_confirmado) !== null }
function render() {
    card.replaceChildren()
    let total = 0n
    let missingPrice = false
    for (const item of pending) {
        const row = document.createElement('div')
        row.className = 'sale-line'
        const description = document.createElement('div')
        const name = document.createElement('strong')
        name.textContent = item.nombre
        const text = document.createElement('small')
        const priced = hasPrice(item)
        if (priced) {
            const subtotal = money.cents(item.precio_confirmado) * BigInt(item.cantidad)
            total += subtotal
            text.textContent = `${item.cantidad} × ${money.amount(item.precio_confirmado)} · ${item.modo === 'Preparado' ? 'Disponible' : 'Por encargo'}`
            const amount = document.createElement('p')
            amount.className = 'sale-subtotal'
            amount.textContent = money.amount(money.decimal(subtotal))
            description.append(name, text, amount)
        } else {
            missingPrice = true
            text.textContent = `${item.cantidad} · Precio pendiente de confirmar`
            description.append(name, text)
        }
        const actions = document.createElement('div')
        actions.className = 'sale-line-actions'
        const refresh = document.createElement('button')
        refresh.type = 'button'; refresh.textContent = 'Actualizar precio'; refresh.disabled = busy || updatingPrice
        refresh.addEventListener('click', () => refreshPrice(item))
        const remove = document.createElement('button')
        remove.type = 'button'; remove.textContent = 'Quitar'; remove.disabled = busy || updatingPrice
        remove.addEventListener('click', () => { pending = pending.filter(p => p !== item); render() })
        actions.append(refresh, remove)
        row.append(description, actions); card.append(row)
    }
    totalLabel.textContent = pending.length ? (missingPrice ? 'Confirma los precios pendientes para ver el total.' : `Total estimado: ${money.amount(money.decimal(total))}`) : ''
    finish.disabled = busy || updatingPrice || !pending.length || missingPrice
    sessionStorage.setItem(storageKey, JSON.stringify(pending))
}
async function refreshPrice(item) {
    if (busy || updatingPrice) return
    updatingPrice = true; render()
    try {
        const res = await fetch('../backend/consulta.php?sku=' + encodeURIComponent(item.sku), {cache:'no-store'})
        const product = await res.json()
        if (!res.ok || !product) throw new Error('El producto ya no existe en el catálogo.')
        if (money.cents(String(product.precio)) === null) throw new Error('El precio del producto no es válido.')
        item.precio_confirmado = money.decimal(money.cents(String(product.precio)))
        item.nombre = product.nombre
        item.producto_id = Number(product.id_articulo)
        status.textContent = 'Precio actualizado. Revisa el total antes de confirmar.'
    } catch (error) { status.textContent = error.message }
    finally { updatingPrice = false; render() }
}
form.addEventListener('submit', async event => {
    event.preventDefault()
    if (busy || updatingPrice) return
    const version = ++searchVersion
    selection.hidden = true; selected = null
    const quantity = Number(document.getElementById('cantidad').value)
    const sku = document.getElementById('sku').value
    if (!Number.isSafeInteger(quantity) || quantity < 1 || quantity > 2147483647) return
    try {
        const res = await fetch('../backend/consulta.php?sku=' + encodeURIComponent(sku), {cache:'no-store'})
        const product = await res.json()
        if (version !== searchVersion || busy || updatingPrice) return
        if (!res.ok || !product) throw new Error('No se encontró el producto.')
        selected = { product, quantity }
        document.getElementById('nombre-producto').textContent = `${product.nombre} · disponibles: ${product.stock} ${unitLabel(product.stock, product.unidad)} · precio: ${money.amount(String(product.precio))}`
        mode.replaceChildren()
        const options = product.categoria !== 'Arreglos' ? ['Preparado'] : product.modalidad === 'Ambas' ? ['Preparado','Por encargo'] : [product.modalidad]
        for (const value of options) {
            if (!['Preparado','Por encargo'].includes(value)) continue
            const option = document.createElement('option')
            option.value = value; option.textContent = value === 'Preparado' ? 'Disponible' : value
            mode.appendChild(option)
        }
        if (!mode.options.length) throw new Error('El Administrador debe configurar la modalidad del arreglo.')
        selection.hidden = false; status.textContent = ''
    } catch (error) { if (version === searchVersion) status.textContent = error.message }
})
document.getElementById('agregar-producto').addEventListener('click', () => {
    if (!selected || busy || updatingPrice) return
    const product = selected.product
    if (pending.some(p => String(p.sku) === String(product.sku) && p.modo === mode.value)) {
        status.textContent = 'Este producto y modalidad ya están en el pedido. Quita la línea para cambiar su cantidad.'; return
    }
    if (mode.value === 'Preparado' && selected.quantity > Number(product.stock)) {
        status.textContent = 'No hay suficientes unidades disponibles.'; return
    }
    if (money.cents(String(product.precio)) === null) { status.textContent = 'El precio no es válido.'; return }
    const now = new Date()
    pending.push({sku:String(product.sku),producto_id:Number(product.id_articulo),nombre:product.nombre,cantidad:selected.quantity,precio_confirmado:money.decimal(money.cents(String(product.precio))),modo:mode.value,fecha:`${now.getFullYear()}-${String(now.getMonth()+1).padStart(2,'0')}-${String(now.getDate()).padStart(2,'0')}`,operacion_id:crypto.randomUUID()})
    selected = null; selection.hidden = true; form.reset(); render()
})
finish.addEventListener('click', async () => {
    if (busy || updatingPrice || !pending.length || pending.some(item => !hasPrice(item))) return
    busy = true; ++searchVersion; selection.hidden = true; render()
    form.querySelectorAll('input,button').forEach(el => el.disabled = true)
    try {
        while (pending.length) {
            const item = pending[0]
            const res = await fetch('../backend/venta.php', {method:'POST',body:new URLSearchParams({...item,csrf:document.getElementById('csrf').value})})
            const result = await res.json()
            if (!res.ok || result.message !== 'OK') throw new Error(result.message || 'No se pudo guardar.')
            pending.shift(); render()
        }
        status.textContent = 'Pedido registrado. Consulta sus importes definitivos en Registros.'
    } catch (error) {
        status.textContent = 'Quedan artículos pendientes. Los ya guardados se retiraron de la lista. ' + error.message
    } finally {
        busy = false; form.querySelectorAll('input,button').forEach(el => el.disabled = false); render()
    }
})
render()
