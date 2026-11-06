
const sku = document.getElementById('sku')
const nombre = document.getElementById('nombre')
const marca = document.getElementById('marca')
const modelo = document.getElementById('modelo')
const departamento = document.getElementById('departamento')
const clase = document.getElementById('clase')
const familia = document.getElementById('familia')
const fechaAlta = document.getElementById('fecha-alta')
const stock = document.getElementById('stock')
const cantidad = document.getElementById('cantidad')
const descontinuado = document.getElementById('descontinuado')
const fechaBaja = document.getElementById('fecha-baja')

async function agregarFamilias(famRes)
{
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

    const familiaChildrenArray = Array.from(familia.children)
    familiaChildrenArray.forEach((child) => {

        if(child.value === famRes.familias[0].num_clase)
        {
            child.setAttribute('selected', true)
        }
    })
}

async function agregarClases(famRes)
{
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

    const claseChildrenArray = Array.from(clase.children)
    claseChildrenArray.forEach((child) => {

        if(child.value === famRes.clases[0].num_clase)
        {
            child.setAttribute('selected', true)
        }
    })

    agregarFamilias(famRes)
}

sku.addEventListener('change', function(e) 
{
    const ui = new UI()

    async function validar(elemento)
    {
        const res = await fetch(`../backend/consulta.php?sku=${elemento}`)
        const response = await res.json()
        
        if (response != false & Object.keys(sku.value).length <= 6 & sku.value != '')
        {   
            // El sku está en la base de datos
            sku.classList.add('is-valid')
            sku.classList.remove('is-invalid')
            sku.disabled = true
            nombre.disabled = false
            marca.disabled = false
            modelo.disabled = false
            departamento.disabled = false
            clase.disabled = false
            familia.disabled = false
            stock.disabled = false
            cantidad.disabled = false
            descontinuado.disabled = false

            nombre.value = response.nombre
            marca.value = response.marca
            modelo.value = response.modelo

            const fam = await fetch(`../backend/consulta.php?departamento=${response.departamento}&clase=${response.clase}&familia=${response.familia}`)
            const famRes = await fam.json()

            const departamentoChildrenArray = Array.from(departamento.children)
            departamentoChildrenArray.forEach((child) => {

                if(child.value === famRes.departamentos[0].id_dep)
                {
                    child.setAttribute('selected', true)
                }
            })

            agregarClases(famRes)

            stock.value = response.stock
            cantidad.value = response.cantidad
            fechaAlta.value = response.fecha_alta
            fechaBaja.value = response.fecha_baja
            descontinuado.value = response.descontinuado


            document.getElementById('error-duplicado').hidden = false
            document.getElementById('error-digitos').hidden = true
        }
        else
        {
            let auxSKU = sku.value
            ui.limpiarFormulario()
            sku.classList.add('is-invalid')
            sku.value = auxSKU

            if(response != false)
            {
                document.getElementById('error-duplicado').hidden = true
            }
            else
            {
                document.getElementById('error-duplicado').hidden = false
            }

            if(Object.keys(sku.value).length > 6)
            {
                document.getElementById('error-digitos').hidden = false
            }
            else
            {
                document.getElementById('error-digitos').hidden = true
            }
        }

    }
    validar(sku.value)
})

document.getElementById('error-en-stock').hidden = false
document.getElementById('error-en-cantidad').hidden = false

stock.addEventListener('change', function(e) 
{
    stockLen = Object.keys(stock.value).length
    document.getElementById('error-en-stock').hidden = true


    if(stockLen <= 9  & stockLen > 0)
    {
        cantidad.disabled = false
        document.getElementById('error-en-stock').hidden = true

        if(cantidad.value == '')
        {
            stock.classList.remove('is-valid')
            stock.classList.remove('is-invalid')
            document.getElementById('error-en-cantidad').hidden = true

        } else if(parseInt(cantidad.value) <= parseInt(stock.value)) 
        {
            stock.classList.add('is-valid')
            cantidad.classList.add('is-valid')
            cantidad.classList.remove('is-invalid')
            document.getElementById('error-en-cantidad').hidden = true
        }
        else
        {
            cantidad.classList.add('is-invalid')
            cantidad.classList.remove('is-valid')
            document.getElementById('error-en-cantidad').hidden = false
        }
    } 
    else if(stockLen <= 0) 
    {

        stock.classList.remove('is-valid')
        stock.classList.remove('is-invalid')
        cantidad.disabled = true
        cantidad.value = ''
        cantidad.classList.remove('is-invalid')
        cantidad.classList.remove('is-valid')
        document.getElementById('error-en-stock').hidden = true
    } 
    else 
    {
        stock.classList.add('is-invalid')
        stock.classList.remove('is-valid')
        cantidad.disabled = true
        cantidad.value = ''
        cantidad.classList.remove('is-invalid')
        cantidad.classList.remove('is-valid')
        document.getElementById('error-en-stock').hidden = false
    }

})

cantidad.addEventListener('change', function(e) 
{
    const cantidadLen =  Object.keys(cantidad.value).length 

    if (cantidad.disabled) 
    {
        cantidad.classList.remove('is-valid')
        cantidad.classList.remove('is-invalid')
        document.getElementById('error-en-cantidad').hidden = true
    } 
    else if(cantidadLen <= 9 & cantidadLen > 0 & parseInt(cantidad.value) <= parseInt(stock.value)) 
    {
        stock.classList.add('is-valid')
        cantidad.classList.add('is-valid')
        cantidad.classList.remove('is-invalid')
        document.getElementById('error-en-cantidad').hidden = true
    } 
    else if(cantidadLen <= 0) 
    {
        cantidad.classList.remove('is-valid')
        cantidad.classList.remove('is-invalid')
        document.getElementById('error-en-cantidad').hidden = true
    } 
    else 
    {
        cantidad.classList.add('is-invalid')
        cantidad.classList.remove('is-valid')
        document.getElementById('error-en-cantidad').hidden = false
    }
})

