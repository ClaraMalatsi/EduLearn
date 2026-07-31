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

    // Daily Motivation card: reads a quote from the external REST API through
    // our own api_proxy/quote_api.php. The card is only on the dashboards, so
    // nothing happens on pages that do not include it.
    const quoteCard = document.getElementById('quoteCard');

    if (quoteCard) {
        const quoteText = document.getElementById('quoteText');
        const quoteAuthor = document.getElementById('quoteAuthor');
        const apiPath = quoteCard.dataset.api;

        fetch(apiPath)
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    quoteText.textContent = '"' + data.quote + '"';
                    quoteAuthor.textContent = '- ' + data.author + ' (via ' + data.source + ')';
                } else {
                    quoteText.textContent = data.message || 'The quote service is unavailable right now.';
                    quoteAuthor.textContent = '';
                }
            })
            .catch(() => {
                // Losing the connection must never break the dashboard.
                quoteText.textContent = 'The quote service could not be reached. Check your internet connection.';
                quoteAuthor.textContent = '';
            });
    }

    document.querySelectorAll('form').forEach(form => {
        form.addEventListener('submit', event => {
            const password = form.querySelector('input[type="password"]');

            // Only check the length when something was actually typed. Forms such
            // as Edit User and My Profile leave the password blank on purpose to
            // keep the current password, and must still be allowed to save.
            if (password && password.value !== '' && password.value.length < 6) {
                event.preventDefault();
                alert('Password must contain at least 6 characters.');
            }
        });
    });
});
