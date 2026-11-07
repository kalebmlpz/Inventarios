const formulario = document.getElementById('formulario-articulos')
const fechaInput = document.getElementById('fecha')
const tabla = document.getElementById('tabla')
const hoy = new Date()
const requestedDate = new URLSearchParams(location.search).get('fecha')
fechaInput.value = requestedDate && /^\d{4}-\d{2}-\d{2}$/.test(requestedDate) ? requestedDate : `${hoy.getFullYear()}-${String(hoy.getMonth() + 1).padStart(2, '0')}-${String(hoy.getDate()).padStart(2, '0')}`
let consultaActual = 0

async function cargarRegistros() {
    const consulta = ++consultaActual
    if (!fechaInput.value) {
        alert('Por favor, seleccione una fecha')
        return
    }
    tabla.innerHTML = '<thead><tr><th>SKU</th><th>Nombre</th><th>Cantidad</th><th>Importe</th><th>Tipo</th><th>Responsable</th><th>Motivo</th><th>Detalle</th></tr></thead><tbody></tbody>'
    const tbody = tabla.querySelector('tbody')
    function mensaje(texto) {
        tbody.replaceChildren()
        const td = document.createElement('td')
        td.colSpan = 8
        td.textContent = texto
        const tr = document.createElement('tr')
        tr.appendChild(td)
        tbody.appendChild(tr)
    }
    mensaje('Cargando registros…')
    try {
        const res = await fetch(`../backend/registros.php?fecha=${encodeURIComponent(fechaInput.value)}`)
        const registros = await res.json()
        if (consulta !== consultaActual) return
        if (!res.ok || !Array.isArray(registros)) throw new Error('No se pudieron cargar los registros')
        tbody.replaceChildren()
        if (registros.length === 0) mensaje('No hay movimientos registrados para esta fecha.')
        for (const registro of registros) {
            const tr = document.createElement('tr')
            for (const valor of [registro.sku, registro.nombre ?? 'Artículo eliminado del catálogo', registro.cantidad, ['Venta','Cancelación','Devolución'].includes(registro.tipo) ? (registro.importe === null ? 'Sin precio registrado' : window.FloreriaMoney.amount(registro.importe, registro.moneda)) : '—', registro.tipo + (registro.modo ? ' · ' + registro.modo : ''), registro.responsable ?? 'Sin autor registrado', registro.motivo || '—']) {
                const td = document.createElement('td')
                td.textContent = valor
                tr.appendChild(td)
            }
            if (registro.precio_unitario !== null) {
                const price = document.createElement('small')
                price.textContent = 'Precio unitario: ' + window.FloreriaMoney.amount(registro.precio_unitario, registro.moneda)
                tr.children[3].appendChild(price)
            }
            const reference = document.createElement('small')
            const corrected = Number(registro.cantidad_corregida || 0)
            reference.textContent = `Folio #${registro.id}`
            if (registro.venta_origen_id) reference.textContent += ` · Venta original #${registro.venta_origen_id}`
            if (registro.tipo === 'Venta' && corrected > 0) reference.textContent += ` · Corregidas: ${corrected} de ${registro.cantidad}`
            tr.children[1].appendChild(reference)
            const cell = document.createElement('td')
            const details = document.createElement('details')
            const summary = document.createElement('summary')
            summary.textContent = 'Ver movimientos'
            details.appendChild(summary)
            for (const movement of registro.movimientos || []) {
                const line = document.createElement('p')
                const quantity = Number(movement.cantidad)
                line.textContent = `${movement.nombre}: ${quantity > 0 ? '+' : ''}${quantity} · stock resultante: ${movement.stock_resultante}`
                details.appendChild(line)
            }
            if (registro.movimientos?.length) cell.appendChild(details)
            else cell.textContent = registro.tratamiento === 'sin_stock' ? 'Sin movimiento de existencias' : 'Registro anterior sin detalle de existencias'
            if (tabla.dataset.admin === '1' && registro.tipo === 'Venta' && Number(registro.cantidad) > corrected) {
                const action = document.createElement('a')
                action.className = 'edit-link correction-link'
                action.href = `devolucion.php?id=${encodeURIComponent(registro.id)}`
                action.textContent = 'Cancelar / Devolver'
                cell.appendChild(action)
            }
            if (registro.tratamiento) {
                const handling = document.createElement('small')
                handling.textContent = ({sin_stock:'Sin reintegro',producto:'Producto reintegrado',captura:'Captura revertida'})[registro.tratamiento] || ''
                cell.appendChild(handling)
            }
            tr.appendChild(cell)
            tbody.appendChild(tr)
        }
    } catch (error) {
        if (consulta === consultaActual) mensaje('No se pudieron cargar los registros. Inténtalo de nuevo.')
    }
}
formulario.addEventListener('submit', function(e) {
    e.preventDefault()
    cargarRegistros()
})
cargarRegistros()
