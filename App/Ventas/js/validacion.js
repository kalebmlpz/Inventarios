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

sku.addEventListener('change', function(e) 
{
    const ui = new UI()

    async function validar(elemento)
    {
        const res = await fetch(`http://localhost/rest/consulta.php?sku=${elemento}`)
        const response = await res.json()
        
        if (response == false & Object.keys(sku.value).length <= 6 & sku.value != '')
        {   
            // El sku está libre
            sku.classList.add('is-valid')
            sku.classList.remove('is-invalid')
            nombre.disabled = false
            marca.disabled = false
            modelo.disabled = false
            departamento.disabled = false
            clase.disabled = false
            familia.disabled = false
            stock.disabled = false
            document.getElementById('error-duplicado').hidden = true
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
                document.getElementById('error-duplicado').hidden = false
            }
            else
            {
                document.getElementById('error-duplicado').hidden = true
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
    const cantidadLen =  Object.keys(cantidad.value).length 
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