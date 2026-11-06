document.getElementById('formulario-articulos')
    .addEventListener('submit', function(e){

    if (guardandoPedido) { e.preventDefault(); return }
    const sku = document.getElementById('sku').value
    const cantidad = document.getElementById('cantidad').value
    const finalizar = document.getElementById('finalizar')

    async function fetchPosts(sku) {
        const res = await fetch(`../backend/consulta.php?sku=${sku}`)
        const response = await res.json()


        if(response != false){
            const card = document.getElementById('articulos')

            const fecha = new Date()
            const fechaString = fecha.toLocaleString('sv-SE', { year: 'numeric', month: '2-digit', day: '2-digit' })
            
            var div = document.createElement("div");
            div.setAttribute("sku", `${sku}`)
            div.setAttribute("cantidad", `${cantidad}`)
            div.setAttribute("fecha", `${fechaString}`)

            var ul = document.createElement("ul")
            ul.classList.add("text-center")

            var li = document.createElement("li")
            li.classList.add("list-unstyled")
            li.innerHTML = `
                <li class="list-unstyled">SKU: ${response.sku}</li class="list-unstyled">
                <li class="list-unstyled">Nombre: ${response.nombre}</li class="list-unstyled">
                <li class="list-unstyled">Marca: ${response.marca}</li class="list-unstyled">
                <li class="list-unstyled">Modelo: ${response.modelo}</li class="list-unstyled">
                <li class="list-unstyled">Cantidad: ${cantidad}</li class="list-unstyled">
                <a href="#" class="btn btn-danger m-2" name="eliminar">Eliminar</a>
                `
            ul.appendChild(li)
            div.appendChild(ul)
            
            let elementExists = false;

            for (let i = 0; i < card.children.length; i++) {
                if (card.children[i].getAttribute('sku') === sku) {
                    elementExists = true;
                    break;
                }
            }
            
            if (elementExists) {
                alert('El elemento ya existe en la lista')
            } else {
                card.appendChild(div);
            }

            if (card.children.length > 0) {
                finalizar.classList.add('btn-success')
                finalizar.classList.remove('btn-dark')
                finalizar.classList.remove('disabled')
            }
            else {
                finalizar.classList.remove('btn-success')
                finalizar.classList.add('btn-dark')
                finalizar.classList.add('disabled')
            }

        }
        else{
            alert('El SKU capturado no se encuentra en la base de datos')
        }      
    }

    if(sku === '' || cantidad === '') alert('Todos los campos son requeridos')
    else{
        fetchPosts(sku)
    }

    e.preventDefault()
})

document.getElementById('articulos').addEventListener('click', function(e) 
{
    const card = document.getElementById('articulos')
    const finalizar = document.getElementById('finalizar')

    if (guardandoPedido) return
    const elemento = e.target

    if(elemento.name === 'eliminar'){
        if(confirm('¿Está seguro que desea eliminar el atrículo?')){
            elemento.parentElement.parentElement.parentElement.remove()
        }
    }

    if (card.children.length > 0) {
        finalizar.classList.add('btn-success')
        finalizar.classList.remove('btn-dark')
        finalizar.classList.remove('disabled')
    }
    else {
        finalizar.classList.remove('btn-success')
        finalizar.classList.add('btn-dark')
        finalizar.classList.add('disabled')
    }
})


let guardandoPedido = false

document.getElementById('finalizar').addEventListener('click', async function(e) {
    e.preventDefault()
    if (guardandoPedido) return
    const card = document.getElementById('articulos')
    const articulos = Array.from(card.children)
    if (articulos.length === 0) return
    guardandoPedido = true
    this.classList.add('disabled')
    try {
        for (const articulo of articulos) {
            const datos = new FormData()
            datos.append('sku', articulo.getAttribute('sku'))
            datos.append('cantidad', articulo.getAttribute('cantidad'))
            datos.append('fecha', articulo.getAttribute('fecha'))
            const response = await fetch('../backend/venta.php', { method: 'POST', body: datos })
            const result = await response.json()
            if (!response.ok || result.message !== 'OK') {
                throw new Error(result.message || 'No se pudo guardar la venta')
            }
            articulo.remove()
        }
        document.getElementById('formulario-articulos').reset()
        alert('Pedido registrado exitosamente. Puedes consultarlo en Registros por la fecha de venta.')
    } catch (error) {
        alert('No se completó el pedido. Los artículos pendientes permanecen en la lista. ' + error.message)
    } finally {
        guardandoPedido = false
        const pendientes = card.children.length > 0
        this.classList.toggle('disabled', !pendientes)
        this.classList.toggle('btn-dark', !pendientes)
        this.classList.toggle('btn-success', pendientes)
    }
})
