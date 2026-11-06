const rows = document.getElementById('recipe-rows')
document.getElementById('add-material').addEventListener('click', () => {
    const row = rows.firstElementChild.cloneNode(true)
    row.querySelector('select').value = ''
    row.querySelector('input').value = '1'
    rows.appendChild(row)
})
rows.addEventListener('click', event => {
    if (!event.target.classList.contains('remove-material')) return
    if (rows.children.length > 1) event.target.closest('.recipe-row').remove()
    else { rows.querySelector('select').value = ''; rows.querySelector('input').value = '1' }
})
