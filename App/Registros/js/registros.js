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
    tabla.innerHTML = '<thead><tr><th>SKU</th><th>Nombre</th><th>Cantidad</th><th>Fecha</th></tr></thead><tbody></tbody>'
    const tbody = tabla.querySelector('tbody')
    function mensaje(texto) {
        tbody.replaceChildren()
        const td = document.createElement('td')
        td.colSpan = 4
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
        if (registros.length === 0) mensaje('No hay ventas registradas para esta fecha.')
        for (const registro of registros) {
            const tr = document.createElement('tr')
            for (const valor of [registro.sku, registro.nombre ?? 'Artículo eliminado del catálogo', registro.cantidad, registro.fecha]) {
                const td = document.createElement('td')
                td.textContent = valor
                tr.appendChild(td)
            }
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
