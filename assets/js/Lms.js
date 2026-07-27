document.addEventListener('DOMContentLoaded', () => {
    const menuButton = document.getElementById('menuButton');
    const sidebar = document.querySelector('.sidebar');
    const themeToggle = document.getElementById('themeToggle');

    if (menuButton && sidebar) {
        menuButton.addEventListener('click', () => {
            sidebar.classList.toggle('open');
        });
    }

    const savedTheme = localStorage.getItem('edulearn-theme');

    if (savedTheme === 'dark') {
        document.body.classList.add('dark-mode');
    }

    function updateThemeIcon() {
        if (!themeToggle) return;

        const icon = themeToggle.querySelector('i');
        if (!icon) return;

        icon.className = document.body.classList.contains('dark-mode')
            ? 'fa-solid fa-sun'
            : 'fa-solid fa-moon';
    }

    updateThemeIcon();

    if (themeToggle) {
        themeToggle.addEventListener('click', () => {
            document.body.classList.toggle('dark-mode');

            localStorage.setItem(
                'edulearn-theme',
                document.body.classList.contains('dark-mode') ? 'dark' : 'light'
            );

            updateThemeIcon();
        });
    }

    document.querySelectorAll('form').forEach(form => {
        form.addEventListener('submit', event => {
            const password = form.querySelector('input[type="password"]');

            if (password && password.value.length < 6) {
                event.preventDefault();
                alert('Password must contain at least 6 characters.');
            }
        });
    });
});
