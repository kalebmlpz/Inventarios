document.getElementById('loginForm')
    .addEventListener('submit', function(e){

    const username = document.getElementById('username').value
    const password = document.getElementById('password').value

    async function fetchPosts(username, password) {
        fetch(`./backend/login.php?username=${username}&password=${password}`)
            .then(function(response) {
                if (response.ok) {
                    window.location.href = './menu.html'
                } else {
                    alert('Usuario o contraseña incorrectos.')
                }
            })
            .catch(function(error) {
                console.log('Se produjo un error:', error)
            })
    }
    fetchPosts(username, password)
    e.preventDefault()
})