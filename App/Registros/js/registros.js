const formulario = document.getElementById('formulario-articulos')
const fechaInput = document.getElementById('fecha')
const tabla = document.getElementById('tabla')
const hoy = new Date()
fechaInput.value = `${hoy.getFullYear()}-${String(hoy.getMonth() + 1).padStart(2, '0')}-${String(hoy.getDate()).padStart(2, '0')}`
let consultaActual = 0

async function cargarRegistros() {
    const consulta = ++consultaActual
    if (!fechaInput.value) {
        alert('Por favor, seleccione una fecha')
        return
    }
    tabla.innerHTML = '<thead><tr><th>SKU</th><th>Nombre</th><th>Cantidad</th><th>Tipo</th><th>Responsable</th><th>Motivo</th><th>Detalle</th></tr></thead><tbody></tbody>'
    const tbody = tabla.querySelector('tbody')
    function mensaje(texto) {
        tbody.replaceChildren()
        const td = document.createElement('td')
        td.colSpan = 7
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
            for (const valor of [registro.sku, registro.nombre ?? 'Artículo eliminado del catálogo', registro.cantidad, registro.tipo + (registro.modo ? ' · ' + registro.modo : ''), registro.responsable ?? 'Sin autor registrado', registro.motivo || '—']) {
                const td = document.createElement('td')
                td.textContent = valor
                tr.appendChild(td)
            }
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
            else cell.textContent = 'Registro anterior sin detalle de existencias'
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
