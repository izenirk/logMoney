/**
 * Роутинг приложения
 */

export function initRouter() {
    window.addEventListener('hashchange', async () => {
        const page = window.location.hash.slice(1) || 'dashboard';
        await window.App.loadPage(page);

        // Закрываем мобильное меню
        if (window.innerWidth <= 768) {
            const sidebar = document.getElementById('sidebar');
            if (sidebar) sidebar.classList.remove('open');
        }
    });

    // Обработка кликов по навигации
    const navLinks = document.querySelectorAll('.nav-link');
    navLinks.forEach(link => {
        link.addEventListener('click', (e) => {
            e.preventDefault();
            const page = link.dataset.page;
            window.location.hash = page;
        });
    });
}

// Функция loadPage будет определена в main.js