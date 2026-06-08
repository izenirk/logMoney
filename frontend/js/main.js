/**
 * Главный файл приложения
 */

// Импортируем только то, что нужно
import { formatMoney, formatDate, formatDateTime, showNotification, getTransactionColor, getAmountSign, getCategoryTypeLabel, debounce } from './modules/utils.js';
import { initModals, openModal, closeModal } from './modules/components/modals.js';
import { initCharts, createPieChart, createBarChart, createLineChart } from './modules/components/charts.js';
import { API } from './modules/api.js';
import { initAuth, loadApp, showAuth } from './modules/auth.js';
import { initRouter } from './modules/router.js';

// Импортируем страницы
import { loadDashboard } from './modules/pages/dashboard.js';
import { loadTransactions } from './modules/pages/transactions.js';
import { loadAccounts } from './modules/pages/accounts.js';
import { loadBudgets } from './modules/pages/budgets.js';
import { loadCategories } from './modules/pages/categories.js';

// Глобальное состояние приложения
window.App = {
    currentUser: null,
    currentPage: 'dashboard',
    api: null,

    // Утилиты
    formatMoney,
    formatDate,
    formatDateTime,
    getTransactionColor,
    getAmountSign,
    getCategoryTypeLabel,
    debounce,

    // Модальные окна
    openModal,
    closeModal,

    // Графики
    createPieChart,
    createBarChart,
    createLineChart,

    // Методы для страниц
    loadDashboard,
    loadTransactions,
    loadAccounts,
    loadBudgets,
    loadCategories,

    // Инициализация
    init: async function() {
        console.log('🚀 Инициализация приложения...');

        // Инициализируем API
        this.api = new API();

        // Инициализируем компоненты
        initModals();
        initCharts();
        initAuth();

        // Привязываем события
        this.bindEvents();

        // Проверяем авторизацию
        await this.checkAuth();
    },

    bindEvents: function() {
        // Мобильное меню
        const mobileBtn = document.getElementById('mobileMenuBtn');
        const sidebar = document.getElementById('sidebar');
        const closeBtn = document.getElementById('closeMenuBtn');

        if (mobileBtn) {
            mobileBtn.onclick = () => sidebar.classList.add('open');
        }
        if (closeBtn) {
            closeBtn.onclick = () => sidebar.classList.remove('open');
        }

        // Закрытие меню при клике вне его
        document.addEventListener('click', (e) => {
            if (window.innerWidth <= 768 && sidebar && mobileBtn) {
                if (!sidebar.contains(e.target) && !mobileBtn.contains(e.target)) {
                    sidebar.classList.remove('open');
                }
            }
        });

        // Выход
        const logoutBtn = document.getElementById('logoutBtn');
        if (logoutBtn) {
            logoutBtn.onclick = async () => {
                try {
                    await this.api.getCsrfCookie();
                    await this.api.logout();
                    showAuth();
                    this.showNotification('Вы вышли из системы', 'success');
                } catch (error) {
                    this.showNotification('Ошибка при выходе', 'error');
                }
            };
        }
    },

    checkAuth: async function() {
        try {
            await this.api.getCsrfCookie();
            const response = await this.api.getUser();

            if (response.success && response.user) {
                this.currentUser = response.user;
                await loadApp();
            } else {
                showAuth();
            }
        } catch (error) {
            showAuth();
        }
    },

    loadPage: async function(page) {
        this.currentPage = page;
        const contentDiv = document.getElementById('pageContent');
        const pageTitle = document.getElementById('pageTitle');

        // Обновляем активную ссылку
        document.querySelectorAll('.nav-link').forEach(link => {
            link.classList.remove('active');
            if (link.dataset.page === page) {
                link.classList.add('active');
            }
        });

        // Загружаем соответствующую страницу
        switch(page) {
            case 'dashboard':
                pageTitle.textContent = 'Главная';
                await this.loadDashboard(contentDiv);
                break;
            case 'transactions':
                pageTitle.textContent = 'Транзакции';
                await this.loadTransactions(contentDiv);
                break;
            case 'accounts':
                pageTitle.textContent = 'Счета';
                await this.loadAccounts(contentDiv);
                break;
            case 'budgets':
                pageTitle.textContent = 'Бюджет';
                await this.loadBudgets(contentDiv);
                break;
            case 'categories':
                pageTitle.textContent = 'Категории';
                await this.loadCategories(contentDiv);
                break;
            default:
                pageTitle.textContent = 'Главная';
                await this.loadDashboard(contentDiv);
        }
    },

    showNotification: function(message, type = 'success') {
        showNotification(message, type);
    }
};

// Запуск приложения после загрузки DOM
document.addEventListener('DOMContentLoaded', () => {
    App.init();
});