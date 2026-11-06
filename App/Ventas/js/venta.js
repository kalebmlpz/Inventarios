document.getElementById('formulario-articulos')
    .addEventListener('submit', function(e){

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

            for (let i = 1; i < card.childNodes.length; i++) {
                if (card.childNodes[i].getAttribute('sku') === sku) {
                    elementExists = true;
                    break;
                }
            }
            
            if (elementExists) {
                alert('El elemento ya existe en la lista')
            } else {
                card.appendChild(div);
            }

            if (card.childNodes.length > 1) {
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

    elemento = e.target

    if(elemento.name === 'eliminar'){
        if(confirm('¿Está seguro que desea eliminar el atrículo?')){
            elemento.parentElement.parentElement.parentElement.remove()
        }
    }

    if (card.childNodes.length > 1) {
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


document.getElementById('finalizar').addEventListener('click', function(e) 
{
    const card = document.getElementById('articulos')
    
    for (let i = 1; i < card.childNodes.length; i++) {
        // console.log(card.childNodes[i])

        const datos = new FormData();
        datos.append('sku', card.childNodes[i].getAttribute('sku'))
        datos.append('cantidad', card.childNodes[i].getAttribute('cantidad'))
        datos.append('fecha', card.childNodes[i].getAttribute('fecha'))

        fetch('../backend/venta.php', {
            method: 'POST',
            body: datos
        })
            .then(response => {
                console.log(response)

                card.innerHTML = ``
                document.getElementById('formulario-articulos').reset()
                finalizar.classList.remove('btn-success')
                finalizar.classList.add('btn-dark')
                finalizar.classList.add('disabled')
                alert('Pedido realizado exitosamente')
            })
            .catch(error => {
                console.log(error)
            })  
    }
})