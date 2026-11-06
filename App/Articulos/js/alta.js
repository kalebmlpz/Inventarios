// Eventos
const fecha = new Date()
const fechaString = fecha.toLocaleString('sv-SE', { year: 'numeric', month: '2-digit', day: '2-digit' })

document.getElementById("fecha-alta").value = fechaString
document.getElementById('formulario-articulos')
    .addEventListener('submit', function(e){
    const sku = document.getElementById('sku').value
    const nombre = document.getElementById('nombre').value
    const marca = document.getElementById('marca').value
    const modelo = document.getElementById('modelo').value
    const departamento = document.getElementById('departamento').value
    const clase = document.getElementById('clase').value
    const familia = document.getElementById('familia').value
    const fechaAlta = document.getElementById('fecha-alta').value
    const stock = document.getElementById('stock').value
    const cantidad = document.getElementById('cantidad').value
    const descontinuado = document.getElementById('descontinuado')
    const fechaBaja = document.getElementById('fecha-baja').value

    const articulo = new Articulo(sku, nombre, marca, modelo, departamento, clase, familia, fechaAlta, stock, cantidad, descontinuado, fechaBaja)
    const ui = new UI()
    e.preventDefault()
    
    if(ui.validarEnviar())
    {
        const ruta = window.location.pathname
        if(ruta.includes('cambio'))
        {
            ui.enviarFormulario(articulo, true)
        }
        else
        {
            ui.enviarFormulario(articulo, false)
        }

        ui.limpiarFormulario()
        return ui.mostrarMensaje('Articulo Agregado', 'success')
    } 
    else
    {
        return ui.mostrarMensaje('Todos los campos deben llenarse correctamente', 'danger')
    }
    
})

document.getElementById('descontinuado').addEventListener('click', function(e) 
{
    if(descontinuado.value == '0')
    {
        descontinuado.value = '1'
        document.getElementById("fecha-baja").value = fechaString
    }
    else
    {
        descontinuado.value = '0'
        document.getElementById("fecha-baja").value = '1900-01-01'
    }
})

document.getElementById('departamento').addEventListener('change', async function(e) 
{
    while (clase.firstChild) 
    {
        clase.removeChild(clase.firstChild)
    }

    while (familia.firstChild) 
    {
        familia.removeChild(familia.firstChild)
    }

    if(!clase.firstChild)
    {
        opClase = document.createElement('option')
        opClase.setAttribute('value', '')
        opClase.setAttribute('disabled', '')
        opClase.setAttribute('selected', '')
        opClase.innerHTML = 'Clase'
        clase.appendChild(opClase)
    }

    if(!familia.firstChild)
    {
        opFamilia = document.createElement('option')
        opFamilia.setAttribute('value', '')
        opFamilia.setAttribute('disabled', '')
        opFamilia.setAttribute('selected', '')
        opFamilia.innerHTML = 'Familia'
        familia.appendChild(opFamilia)
    
    }

    const res = await fetch(`../backend/consulta.php?departamento=${departamento.value}`)
    const response = await res.json()


    let i = 1
    response.forEach(e => {
        const elemento = document.createElement('option')
        elemento.setAttribute('value', i)
        elemento.innerHTML = e.nombre_clase
        
        clase.appendChild(elemento)

        i++
    })

})

document.getElementById('clase').addEventListener('change', async function(e) {

    while (familia.firstChild) {
        familia.removeChild(familia.firstChild)
    }

    if(!familia.firstChild)
    {
        opFamilia = document.createElement('option')
        opFamilia.setAttribute('value', '')
        opFamilia.setAttribute('disabled', '')
        opFamilia.setAttribute('selected', '')
        opFamilia.innerHTML = 'Familia'
        familia.appendChild(opFamilia)
    }

    const res = await fetch(`../backend/consulta.php?departamento=${departamento.value}&clase=${clase.value}`)
    const response = await res.json()

    let i = Object.keys(response).length
    response.forEach(e => {
        const elemento = document.createElement('option')
        elemento.setAttribute('value', i)
        elemento.innerHTML = e.nombre_familia
        
        familia.insertBefore(elemento, familia.firstChild)
        i--
    })

})

async function agregarDepartamentos()
{
    opDepartamento = document.createElement('option')
    opDepartamento.setAttribute('value', '')
    opDepartamento.setAttribute('disabled', '')
    opDepartamento.setAttribute('selected', '')
    opDepartamento.innerHTML = 'Departamento'
    departamento.appendChild(opDepartamento)

    opClase = document.createElement('option')
    opClase.setAttribute('value', '')
    opClase.setAttribute('disabled', '')
    opClase.setAttribute('selected', '')
    opClase.innerHTML = 'Clase'
    clase.appendChild(opClase)

    opFamilia = document.createElement('option')
    opFamilia.setAttribute('value', '')
    opFamilia.setAttribute('disabled', '')
    opFamilia.setAttribute('selected', '')
    opFamilia.innerHTML = 'Familia'
    familia.appendChild(opFamilia)


    const listaDepartamentos = document.getElementById('departamento')

    const res = await fetch(`../backend/consulta.php?lista_departamentos`)
    const response = await res.json()

    let i = 1
    response.forEach(e => {
        const elemento = document.createElement('option')
        elemento.setAttribute('value', i)
        elemento.innerHTML = e.nombre_departamento
        
        listaDepartamentos.appendChild(elemento)
        i++
    })
}

agregarDepartamentos()
