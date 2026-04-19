document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('loginForm');
    const errorMessage = document.getElementById('errorMessage');

    if (!form) {
        return;
    }

    form.addEventListener('submit', function (event) {
        errorMessage.innerText = '';
        const username = document.getElementById('username').value.trim();
        const password = document.getElementById('password').value;
        const errors = [];

        if (username.length === 0) {
            errors.push('Username is required.');
        }

        if (password.length === 0) {
            errors.push('Password is required.');
        }

        if (errors.length > 0) {
            event.preventDefault();
            errorMessage.innerText = errors.join(' ');
        }
    });
});
