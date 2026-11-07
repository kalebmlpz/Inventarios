const categoria = document.getElementById('categoria')
const arregloOptions = document.getElementById('arreglo-options')
function actualizarModalidad() {
    const esArreglo = categoria.value === 'Arreglos'
    arregloOptions.hidden = !esArreglo
    const modalidad = document.getElementById('modalidad')
    modalidad.querySelector('option[value="Individual"], option:first-child').disabled = esArreglo
    if (esArreglo && modalidad.value === 'Individual') modalidad.value = 'Preparado'
    if (!esArreglo) modalidad.value = 'Individual'
    document.getElementById('stock').readOnly = esArreglo || Number(document.querySelector('input[name="id"]').value) > 0
}
categoria.addEventListener('change', actualizarModalidad)
actualizarModalidad()
