/**
 * Модуль авторизации
 */

export function initAuth() {
    // Переключение табов
    const tabBtns = document.querySelectorAll('.tab-btn');
    if (tabBtns.length) {
        tabBtns.forEach(btn => {
            btn.addEventListener('click', () => {
                const tab = btn.dataset.tab;
                tabBtns.forEach(b => b.classList.remove('active'));
                btn.classList.add('active');

                const forms = document.querySelectorAll('.auth-form');
                forms.forEach(form => form.classList.remove('active'));
                document.getElementById(`${tab}-form`).classList.add('active');
            });
        });
    }

    // Обработка формы входа
    const loginForm = document.getElementById('login-form');
    if (loginForm) {
        loginForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const email = document.getElementById('login-email').value;
            const password = document.getElementById('login-password').value;

            try {
                await window.App.api.getCsrfCookie();
                const response = await window.App.api.login({ email, password });

                if (response.success) {
                    window.App.currentUser = response.user;
                    await loadApp();
                    window.App.showNotification('Добро пожаловать!', 'success');
                } else {
                    window.App.showNotification(response.message, 'error');
                }
            } catch (error) {
                window.App.showNotification(error.response?.data?.message || 'Ошибка при входе', 'error');
            }
        });
    }

    // Обработка формы регистрации
    const registerForm = document.getElementById('register-form');
    if (registerForm) {
        registerForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const name = document.getElementById('register-name').value;
            const email = document.getElementById('register-email').value;
            const password = document.getElementById('register-password').value;
            const passwordConfirmation = document.getElementById('register-password-confirmation').value;

            if (password !== passwordConfirmation) {
                window.App.showNotification('Пароли не совпадают', 'error');
                return;
            }

            try {
                await window.App.api.getCsrfCookie();
                const response = await window.App.api.register({ name, email, password, password_confirmation: passwordConfirmation });

                if (response.success) {
                    window.App.currentUser = response.user;
                    await loadApp();
                    window.App.showNotification('Регистрация успешна!', 'success');
                } else {
                    const errors = response.errors;
                    const errorMessage = errors ? Object.values(errors).flat().join(', ') : response.message;
                    window.App.showNotification(errorMessage, 'error');
                }
            } catch (error) {
                window.App.showNotification(error.response?.data?.message || 'Ошибка при регистрации', 'error');
            }
        });
    }
}

export async function loadApp() {
    // Скрываем экран авторизации
    document.getElementById('auth-screen').style.display = 'none';
    document.getElementById('app-screen').style.display = 'flex';

    // Показываем имя пользователя
    const userNameElement = document.getElementById('userName');
    if (userNameElement && window.App.currentUser) {
        userNameElement.textContent = window.App.currentUser.name;
    }

    // Инициализируем роутинг
    const { initRouter } = await import('./router.js');
    initRouter();

    // Загружаем стартовую страницу
    const hash = window.location.hash.slice(1) || 'dashboard';
    await window.App.loadPage(hash);
}

export function showAuth() {
    document.getElementById('auth-screen').style.display = 'flex';
    document.getElementById('app-screen').style.display = 'none';
    window.App.currentUser = null;

    // Очищаем формы
    const loginForm = document.getElementById('login-form');
    const registerForm = document.getElementById('register-form');
    if (loginForm) loginForm.reset();
    if (registerForm) registerForm.reset();
}

// Не используем checkAuth, так как эта логика в main.js