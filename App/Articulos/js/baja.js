document.getElementById('formulario-articulos')
    .addEventListener('submit', function(e){

    const sku = document.getElementById('sku').value

    len = Object.keys(sku).length
    const ui = new UI()

    async function fetchPosts(sku) {
        const res = await fetch(`../backend/consulta.php?sku=${sku}`)
        const response = await res.json()
    
        if(response != false){
            const card = document.getElementById('lista-productos')
            card.innerHTML = `
                <div class="card text-center" sku="${sku}">
                    <ul class="card-body">
                        <li class="list-unstyled">SKU: ${response.sku}</li class="list-unstyled">
                        <li class="list-unstyled">Nombre: ${response.nombre}</li class="list-unstyled">
                        <li class="list-unstyled">Marca: ${response.marca}</li class="list-unstyled">
                        <li class="list-unstyled">Modelo: ${response.modelo}</li class="list-unstyled">

                        <a href="#" class="btn btn-danger m-2" name="eliminar">Eliminar</a>

                    </ul>
                </div>
                `
        }
        else{
            alert('El SKU capturado no se encuentra en la base de datos')
        }      
    }

    if(sku === '') return ui.mostrarMensaje('Todos los campos son requeridos', 'danger')
    if(len > 6) return ui.mostrarMensaje('El SKU no debe superar los 6 dígitos', 'danger')
    else{
        fetchPosts(sku)
    }
    e.preventDefault()
})

async function eliminar(sku){
    const ui = new UI()
    fetch(`../backend/baja.php?sku=${sku}`, {
        method: 'DELETE'
        }).then(response => {
            console.log(response)
            ui.mostrarMensaje('Artículo eliminado', 'danger')
        })
}

document.getElementById('lista-productos').addEventListener('click', function(e) 
{
    elemento = e.target

    if(elemento.name === 'eliminar'){
        if(confirm('¿Está seguro que desea eliminar el atrículo?')){
            const sku = document.getElementById('sku').value
            eliminar(sku)
            elemento.parentElement.parentElement.parentElement.remove()
        }
    }
})