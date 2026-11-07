document.getElementById('loginForm')
    .addEventListener('submit', function(e){

    const username = document.getElementById('username').value
    const password = document.getElementById('password').value

    async function fetchPosts(username, password) {
        fetch('./backend/login.php', {
            method: 'POST',
            body: new URLSearchParams({ username, password })
        })
            .then(async function(response) {
                const result = await response.json()
                if (response.ok && result.message === 'OK') {
                    window.location.href = './menu.php'
                } else {
                    alert(result.message || 'Usuario o contraseña incorrectos.')
                }
            })
            .catch(function(error) {
                alert('No se pudo iniciar sesión. Inténtalo de nuevo.')
            })
    }
    fetchPosts(username, password)
    e.preventDefault()
})
if (new URLSearchParams(location.search).get('cuenta') === 'actualizada') {
    const notice = document.createElement('p')
    notice.setAttribute('role', 'status')
    notice.textContent = 'Tu cuenta se actualizó. Inicia sesión nuevamente.'
    document.getElementById('loginForm').prepend(notice)
}
