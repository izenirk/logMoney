/**
 * Страница счетов
 */

export async function loadAccounts(container) {
    try {
        container.innerHTML = '<div class="loading">Загрузка...</div>';

        const response = await window.App.api.getAccounts();

        // Проверяем структуру ответа
        let accounts = [];
        let totalBalance = 0;

        if (response && response.success) {
            accounts = response.data || [];
            totalBalance = response.total_balance || 0;
        } else if (Array.isArray(response)) {
            accounts = response;
            totalBalance = accounts.reduce((sum, acc) => sum + (acc.balance || 0), 0);
        } else if (response && response.data) {
            accounts = response.data;
            totalBalance = accounts.reduce((sum, acc) => sum + (acc.balance || 0), 0);
        }

        container.innerHTML = `
            <div style="margin-bottom: 20px; display: flex; justify-content: flex-end;">
                <button class="btn-primary" id="showAddAccountModalBtn">
                    ➕ Добавить счет
                </button>
            </div>
            
            <div class="stats-grid" style="margin-bottom: 24px;">
                <div class="stat-card">
                    <div class="stat-card-header">
                        <span class="stat-card-title">Общий баланс</span>
                        <span class="stat-card-icon">💰</span>
                    </div>
                    <div class="stat-card-value">${window.App.formatMoney(totalBalance)} ₽</div>
                    <div class="stat-card-change">По всем счетам</div>
                </div>
                <div class="stat-card">
                    <div class="stat-card-header">
                        <span class="stat-card-title">Количество счетов</span>
                        <span class="stat-card-icon">🏦</span>
                    </div>
                    <div class="stat-card-value">${accounts.length}</div>
                    <div class="stat-card-change">Активных счетов</div>
                </div>
            </div>
            
            <div class="table-container">
                <h3>🏦 Мои счета</h3>
                <div id="accountsList">
                    ${renderAccountsTable(accounts)}
                </div>
            </div>
        `;

        // Модальное окно для добавления счета
        const modalContent = `
            <form id="addAccountForm">
                <div class="form-group">
                    <label class="required">Название счета</label>
                    <input type="text" id="accountName" placeholder="Наличные, Карта, Копилка..." required>
                </div>
                <div class="form-group">
                    <label class="required">Тип счета</label>
                    <select id="accountType" required>
                        <option value="cash">💵 Наличные</option>
                        <option value="card">💳 Банковская карта</option>
                        <option value="bank">🏦 Банковский счет</option>
                        <option value="electronic">📱 Электронный кошелек</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="required">Начальный баланс</label>
                    <input type="number" id="accountBalance" step="0.01" value="0" required>
                </div>
                <div class="form-group">
                    <label>Валюта</label>
                    <select id="accountCurrency">
                        <option value="RUB">₽ Рубль</option>
                        <option value="USD">$ Доллар</option>
                        <option value="EUR">€ Евро</option>
                    </select>
                </div>
                <div style="display: flex; gap: 10px; margin-top: 20px;">
                    <button type="submit" class="btn-primary">✅ Создать</button>
                    <button type="button" class="btn-secondary" id="closeModalBtn">Отмена</button>
                </div>
            </form>
        `;

        const showModalBtn = document.getElementById('showAddAccountModalBtn');
        if (showModalBtn) {
            showModalBtn.addEventListener('click', () => {
                window.openModal('➕ Новый счет', modalContent);

                const form = document.getElementById('addAccountForm');
                if (form) {
                    form.addEventListener('submit', async (e) => {
                        e.preventDefault();
                        await createNewAccount();
                    });
                }

                const closeBtn = document.getElementById('closeModalBtn');
                if (closeBtn) {
                    closeBtn.addEventListener('click', () => {
                        window.closeModal();
                    });
                }
            });
        }

        // Обработчики для кнопок удаления
        setTimeout(() => {
            document.querySelectorAll('.delete-account').forEach(btn => {
                btn.addEventListener('click', async () => {
                    const id = btn.dataset.id;
                    const name = btn.dataset.name;
                    if (confirm(`Удалить счет "${name}"?`)) {
                        try {
                            await window.App.api.deleteAccount(id);
                            window.App.showNotification('✅ Счет удален', 'success');
                            await loadAccounts(container);
                        } catch (error) {
                            const message = error.response?.data?.message || 'Ошибка удаления';
                            window.App.showNotification(`❌ ${message}`, 'error');
                        }
                    }
                });
            });
        }, 100);

    } catch (error) {
        console.error('Error loading accounts:', error);
        container.innerHTML = `
            <div class="error">
                ❌ Ошибка загрузки счетов<br>
                <small>${error.message || 'Проверьте подключение к серверу'}</small>
            </div>
        `;
    }
}

function renderAccountsTable(accounts) {
    if (!accounts || accounts.length === 0) {
        return '<div class="empty-state"><div class="empty-icon">🏦</div><h4>Нет счетов</h4><p>Создайте первый счет с помощью кнопки выше</p></div>';
    }

    const getTypeIcon = (type) => {
        const icons = {
            cash: '💵',
            card: '💳',
            bank: '🏦',
            electronic: '📱'
        };
        return icons[type] || '💰';
    };

    const getTypeName = (type) => {
        const names = {
            cash: 'Наличные',
            card: 'Карта',
            bank: 'Банковский счет',
            electronic: 'Электронный'
        };
        return names[type] || type;
    };

    return `
        <table>
            <thead>
                <tr><th>Название</th><th>Тип</th><th>Баланс</th><th>Действия</th></tr>
            </thead>
            <tbody>
                ${accounts.map(acc => `
                    <tr>
                        <td><strong>${acc.name}</strong></td>
                        <td>${getTypeIcon(acc.type)} ${getTypeName(acc.type)}</td>
                        <td class="${acc.balance >= 0 ? 'text-success' : 'text-danger'}">${window.App.formatMoney(acc.balance)} ₽</td>
                        <td>
                            <button class="delete-account btn-icon" data-id="${acc.id}" data-name="${acc.name}" style="background:none; border:none; cursor:pointer; color:#ef4444; font-size:18px;" title="Удалить">🗑️</button>
                        </td>
                    </tr>
                `).join('')}
            </tbody>
        </table>
    `;
}

async function createNewAccount() {
    const data = {
        name: document.getElementById('accountName').value,
        type: document.getElementById('accountType').value,
        balance: parseFloat(document.getElementById('accountBalance').value) || 0,
        currency: document.getElementById('accountCurrency').value
    };

    if (!data.name) {
        window.App.showNotification('❌ Введите название счета', 'error');
        return;
    }

    try {
        await window.App.api.createAccount(data);
        window.App.showNotification('✅ Счет создан', 'success');
        window.closeModal();
        await loadAccounts(document.getElementById('pageContent'));
    } catch (error) {
        const message = error.response?.data?.message || 'Ошибка создания счета';
        window.App.showNotification(`❌ ${message}`, 'error');
    }
}