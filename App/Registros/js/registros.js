document.getElementById('formulario-articulos')
    .addEventListener('submit', function(e){

    const fecha = document.getElementById('fecha').value
    const tabla = document.getElementById('tabla')

    async function fetchGets(fecha) {
        if(fecha === '') alert('Por favor, seleccione una fecha')
        else {
            const res = await fetch(`../backend/registros.php?fecha=${fecha}`)
            const response = await res.json()

            tabla.innerHTML = `
                <thead>
                    <tr class="bg-primary text-white">
                        <th scope="col">sku</th>
                        <th scope="col">nombre</th>
                        <th scope="col">cantidad</th>
                        <th scope="col">fecha</th>
                    </tr>
                </thead>
            `

            var tbody = document.createElement("tbody");

            for (let i = 0; i < Object.keys(response).length; i++) {
                const res2 = await fetch(`../backend/consulta.php?sku=${response[i].sku}`)
                const response2 = await res2.json()

                var tr = document.createElement('tr')
                tr.classList.add('table-active')

                // var th = document.createElement('th')
                // th.setAttribute('scope', 'row')
                // tr.appendChild(th)
                
                var sku = document.createElement('td')
                sku.innerHTML = `${response[i].sku}`
                tr.appendChild(sku)

                var nombre = document.createElement('td')
                nombre.innerHTML = `${response2.nombre}`
                tr.appendChild(nombre)

                var cantidad = document.createElement('td')
                cantidad.innerHTML = `${response[i].cantidad}`
                tr.appendChild(cantidad)

                var fecha = document.createElement('td')
                fecha.innerHTML = `${response[i].fecha}`
                tr.appendChild(fecha)


                tbody.appendChild(tr)
                // tabla.appendChild(tbody)
            }
            tabla.appendChild(tbody)
            console.log(response)
            
        }
    }

    fetchGets(fecha)
    e.preventDefault()
})