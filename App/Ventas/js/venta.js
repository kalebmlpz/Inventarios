const form = document.getElementById('formulario-articulos')
const selection = document.getElementById('seleccion-producto')
const card = document.getElementById('articulos')
const finish = document.getElementById('finalizar')
const status = document.getElementById('estado-venta')
const mode = document.getElementById('modo')
const storageKey = 'floreria-pedido-' + document.getElementById('usuario').value
let pending = []
try { pending = JSON.parse(sessionStorage.getItem(storageKey) || '[]'); if (!Array.isArray(pending)) pending = [] } catch { pending = [] }
let selected = null
let busy = false
let searchVersion = 0
function render() {
    card.replaceChildren()
    for (const item of pending) {
        const row = document.createElement('div')
        row.className = 'sale-line'
        const text = document.createElement('p')
        text.textContent = `${item.nombre} · ${item.cantidad} · ${item.modo === 'Preparado' ? 'Disponible' : 'Por encargo'}`
        const remove = document.createElement('button')
        remove.type = 'button'; remove.textContent = 'Quitar'; remove.disabled = busy
        remove.addEventListener('click', () => { pending = pending.filter(p => p !== item); render() })
        row.append(text, remove); card.append(row)
    }
    finish.disabled = busy || !pending.length
    sessionStorage.setItem(storageKey, JSON.stringify(pending))
}
form.addEventListener('submit', async event => {
    event.preventDefault()
    if (busy) return
    const version = ++searchVersion
    selection.hidden = true; selected = null
    const quantity = Number(document.getElementById('cantidad').value)
    const sku = document.getElementById('sku').value
    if (!Number.isInteger(quantity) || quantity < 1) return
    try {
        const res = await fetch('../backend/consulta.php?sku=' + encodeURIComponent(sku), {cache:'no-store'})
        const product = await res.json()
        if (version !== searchVersion || busy) return
        if (!res.ok || !product) throw new Error('No se encontró el producto.')
        selected = { product, quantity }
        document.getElementById('nombre-producto').textContent = `${product.nombre} · disponibles: ${product.stock} ${product.unidad}`
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
    if (!selected || busy) return
    const product = selected.product
    if (pending.some(p => String(p.sku) === String(product.sku) && p.modo === mode.value)) {
        status.textContent = 'Este producto y modalidad ya están en el pedido. Quita la línea para cambiar su cantidad.'; return
    }
    if (mode.value === 'Preparado' && selected.quantity > Number(product.stock)) {
        status.textContent = 'No hay suficientes unidades disponibles.'; return
    }
    const now = new Date()
    pending.push({sku:String(product.sku),nombre:product.nombre,cantidad:selected.quantity,modo:mode.value,fecha:`${now.getFullYear()}-${String(now.getMonth()+1).padStart(2,'0')}-${String(now.getDate()).padStart(2,'0')}`,operacion_id:crypto.randomUUID()})
    selected = null; selection.hidden = true; form.reset(); render()
})
finish.addEventListener('click', async () => {
    if (busy || !pending.length) return
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
        status.textContent = 'Pedido registrado. Las existencias y el historial están actualizados.'
    } catch (error) {
        status.textContent = 'Quedan artículos pendientes. Los ya guardados se retiraron de la lista. ' + error.message
    } finally {
        busy = false; form.querySelectorAll('input,button').forEach(el => el.disabled = false); render()
    }
})
render()
