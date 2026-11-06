class Articulo {
    constructor(sku, nombre, marca, modelo, departamento, clase, familia, fechaAlta, stock, cantidad, descontinuado, fechaBaja){
        this.sku = sku
        this.nombre = nombre
        this.marca = marca
        this.modelo = modelo
        this.departamento = departamento
        this.clase = clase
        this.familia = familia
        this.fechaAlta = fechaAlta
        this.stock = stock
        this.cantidad = cantidad
        this.descontinuado = descontinuado
        this.fechaBaja = fechaBaja
    }
}

class UI {
    enviarFormulario(articulo, cambio)
    {
        const datos = new FormData();
        datos.append('sku', articulo.sku)
        datos.append('nombre', articulo.nombre)
        datos.append('marca', articulo.marca)
        datos.append('modelo', articulo.modelo)
        datos.append('departamento', articulo.departamento)
        datos.append('clase', articulo.clase)
        datos.append('familia', articulo.familia)
        datos.append('fecha_alta', articulo.fechaAlta)
        datos.append('stock', articulo.stock)
        datos.append('cantidad', articulo.cantidad)
        datos.append('descontinuado', articulo.descontinuado.value)
        datos.append('fecha_baja', articulo.fechaBaja)

        if(cambio === true)
        {
            fetch(`../backend/baja.php?sku=${articulo.sku}`, {
                method: 'DELETE'
            })
                .then(response => {
                    console.log(response)
                    fetch('../backend/alta.php', {
                        method: 'POST',
                        body: datos
                    })
                        .then(response => {
                            console.log(response)
                        })
                        .catch(error => {
                            console.log(error)
                        })
                })
                .catch(error => {
                    console.log(error)
                }) 
        }
        else
        {
            fetch('../backend/alta.php', {
                method: 'POST',
                body: datos
            })
                .then(response => {
                    console.log(response)
                })
                .catch(error => {
                    console.log(error)
                })  

            sku.disabled = false
        }
    }

    limpiarFormulario()
    {
        sku.classList.remove('is-valid')
        stock.classList.remove('is-valid')
        cantidad.classList.remove('is-valid')
        sku.classList.remove('is-invalid')
        stock.classList.remove('is-invalid')
        cantidad.classList.remove('is-invalid')

        nombre.disabled = true
        marca.disabled = true
        modelo.disabled = true
        departamento.disabled = true
        clase.disabled = true
        familia.disabled = true
        stock.disabled = true
        cantidad.disabled = true
        descontinuado.disabled = true
        
        const fecha = new Date()
        const fechaString = fecha.toLocaleString('sv-SE', { year: 'numeric', month: '2-digit', day: '2-digit' })
        document.getElementById('formulario-articulos').reset()
        document.getElementById("fecha-alta").value = fechaString
        fechaBaja.value = '1900-01-01'
    }

    eliminarArticulo(elemento)
    {
        
        if(elemento.name === 'eliminar')
        {
            if(confirm('¿Está seguro que desea eliminar el atrículo?'))
            {
                const id = elemento.parentElement.parentElement.id
                fetch(`../backend/baja.php?id_articulo=${id}`, {
                    method: 'DELETE'
                })
                    .then(response => {
                        elemento.parentElement.parentElement.parentElement.remove()
                        this.mostrarMensaje('Artículo eliminado', 'danger')
                        console.log(response)
                    })
                    .catch(error => {
                        console.log(error)
                    })
            }
        }   
    }

    mostrarMensaje(mensaje, caseCSS)
    {
        const div = document.createElement('div')
        div.className = `alert alert-${caseCSS} mt-2`
        div.appendChild(document.createTextNode(mensaje))

        const contenedor = document.querySelector('.container-sm')
        const app = document.querySelector('#App')
        contenedor.insertBefore(div, app)
        setTimeout(function() {
            document.querySelector('.alert').remove()
        }, 5000)
    }

    validarEnviar()
    {
        const elementos = [nombre.value, marca.value, modelo.value, departamento.value, clase.value, familia.value,];
        let algunVacio = false;

        elementos.forEach(elemento => {
            if (elemento === '') 
            {
                algunVacio = true
            }
        })

        if(algunVacio == false & parseInt(cantidad.value) <= parseInt(stock.value))
        {
            return true
        }
        else
        {
            return false
        }
    }

    departamentoComboBox()
    {
        const listaDepartamentos = document.getElementById('departamento')
        const elemento = document.createElement('option')
        elemento.setAttribute(`value', '${i}`)
        elemento.innerHTML = 'TRES'
        
        listaDepartamentos.appendChild(elemento)
    }
}