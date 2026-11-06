document.getElementById('formulario-articulos')
    .addEventListener('submit', function(e){

    const sku = document.getElementById('sku').value

    len = Object.keys(sku).length
    const ui = new UI()

    async function fetchPosts(sku) {
        const res = await fetch(`http://localhost/rest/consulta.php?sku=${sku}`)
        const response = await res.json()
    
        const fam = await fetch(`http://localhost/rest/consulta.php?departamento=${response.departamento}&clase=${response.clase}&familia=${response.familia}`)
        const famRes = await fam.json()

        if(response != false){
            const card = document.getElementById('lista-productos')
            card.innerHTML = `
                <div class="card text-center" sku="${sku}">
                    <ul class="card-body">
                        <li class="list-unstyled">SKU: ${response.sku}</li class="list-unstyled">
                        <li class="list-unstyled">Nombre: ${response.nombre}</li class="list-unstyled">
                        <li class="list-unstyled">Marca: ${response.marca}</li class="list-unstyled">
                        <li class="list-unstyled">Modelo: ${response.modelo}</li class="list-unstyled">
                        <li class="list-unstyled">Departamento: ${famRes.departamentos[0].nombre_departamento}</li class="list-unstyled">
                        <li class="list-unstyled">Clase: ${famRes.clases[0].nombre_clase}</li class="list-unstyled">
                        <li class="list-unstyled">Familia: ${famRes.familias[0].nombre_familia}</li class="list-unstyled">
                        <li class="list-unstyled">Fecha Alta: ${response.fecha_alta}</li class="list-unstyled">
                        <li class="list-unstyled">Stock: ${response.stock}</li class="list-unstyled">
                        <li class="list-unstyled">Cantidad: ${response.cantidad}</li class="list-unstyled">
                        <li class="list-unstyled">Descontinuado: ${response.descontinuado}</li class="list-unstyled">
                        <li class="list-unstyled">Fecha Baja: ${response.fecha_baja}</li class="list-unstyled">
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