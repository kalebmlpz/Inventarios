const categoria = document.getElementById('categoria')
const arregloOptions = document.getElementById('arreglo-options')
function actualizarModalidad() {
    arregloOptions.hidden = categoria.value !== 'Arreglos'
}
categoria.addEventListener('change', actualizarModalidad)
actualizarModalidad()
