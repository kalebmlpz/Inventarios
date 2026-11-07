const type = document.getElementById('tipo')
const treatment = document.getElementById('tratamiento')
function actualizarOpciones() {
    const correction = treatment.querySelector('option[value="captura"]')
    correction.disabled = type.value === 'Devolución'
    if (correction.disabled && treatment.value === 'captura') treatment.value = 'sin_stock'
}
type.addEventListener('change', actualizarOpciones)
actualizarOpciones()
