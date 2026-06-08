/**
 * Страница транзакций
 */

let currentTransactionsPage = 1;
let transactionsFilters = {};

export async function loadTransactions(container) {
    try {
        container.innerHTML = '<div class="loading">Загрузка...</div>';

        // Загружаем категории, счета и транзакции
        const [categoriesRes, accountsRes, transactionsRes] = await Promise.all([
            window.App.api.getCategories(),
            window.App.api.getAccounts(),
            window.App.api.getTransactions({ page: currentTransactionsPage, ...transactionsFilters })
        ]);

        const categories = categoriesRes?.data || [];
        const accounts = accountsRes?.data || [];
        const transactions = transactionsRes?.data || [];

        // Проверяем, есть ли категории и счета
        if (categories.length === 0) {
            container.innerHTML = `
                <div class="error">
                    ❌ Нет категорий<br>
                    <small>Сначала создайте категории в разделе "Категории"</small>
                </div>
            `;
            return;
        }

        if (accounts.length === 0) {
            container.innerHTML = `
                <div class="error">
                    ❌ Нет счетов<br>
                    <small>Сначала создайте счет в разделе "Счета"</small>
                </div>
            `;
            return;
        }

        container.innerHTML = `
            <!-- Форма создания транзакции -->
            <div class="form-card">
                <h3>➕ Новая транзакция</h3>
                <form id="transactionForm">
                    <div class="form-row">
                        <div class="form-group">
                            <label class="required">Тип</label>
                            <select id="transType" required>
                                <option value="expense">📉 Расход</option>
                                <option value="income">📈 Доход</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="required">Категория</label>
                            <select id="transCategory" required></select>
                        </div>
                        <div class="form-group">
                            <label class="required">Счет</label>
                            <select id="transAccount" required></select>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="required">Сумма</label>
                            <input type="number" id="transAmount" step="0.01" placeholder="0.00" required>
                        </div>
                        <div class="form-group">
                            <label class="required">Дата</label>
                            <input type="date" id="transDate" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Описание</label>
                        <input type="text" id="transDescription" placeholder="Например: Покупка продуктов">
                    </div>
                    <div class="form-group">
                        <label>Заметка</label>
                        <textarea id="transNote" rows="2" placeholder="Дополнительная информация..."></textarea>
                    </div>
                    <button type="submit" class="btn-primary">✅ Создать транзакцию</button>
                </form>
            </div>
            
            <!-- Фильтры -->
            <div class="form-card">
                <h3>🔍 Фильтры</h3>
                <div class="form-row">
                    <div class="form-group">
                        <label>Тип</label>
                        <select id="filterType">
                            <option value="">Все</option>
                            <option value="income">📈 Доходы</option>
                            <option value="expense">📉 Расходы</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Категория</label>
                        <select id="filterCategory">
                            <option value="">Все</option>
                            ${categories.map(c => `<option value="${c.id}">${c.icon || '📁'} ${c.name}</option>`).join('')}
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Счет</label>
                        <select id="filterAccount">
                            <option value="">Все</option>
                            ${accounts.map(a => `<option value="${a.id}">🏦 ${a.name}</option>`).join('')}
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Дата от</label>
                        <input type="date" id="filterDateFrom">
                    </div>
                    <div class="form-group">
                        <label>Дата до</label>
                        <input type="date" id="filterDateTo">
                    </div>
                </div>
                <div style="display: flex; gap: 10px;">
                    <button class="btn-secondary" id="applyFilters">🔍 Применить</button>
                    <button class="btn-secondary" id="resetFilters">🔄 Сбросить</button>
                </div>
            </div>
            
            <!-- Список транзакций -->
            <div class="table-container">
                <h3>📋 История транзакций</h3>
                <div id="transactionsList">
                    ${renderTransactionsTable(transactions.data || [])}
                </div>
                ${renderTransactionsPagination(transactions)}
            </div>
        `;

        // Заполняем select'ы
        const categorySelect = document.getElementById('transCategory');
        const accountSelect = document.getElementById('transAccount');

        // Разделяем категории по типу
        const incomeCategories = categories.filter(c => c.type === 'income');
        const expenseCategories = categories.filter(c => c.type === 'expense');

        // Заполняем категории для расходов (по умолчанию)
        expenseCategories.forEach(cat => {
            categorySelect.innerHTML += `<option value="${cat.id}" data-type="expense">${cat.icon || '📁'} ${cat.name}</option>`;
        });
        incomeCategories.forEach(cat => {
            categorySelect.innerHTML += `<option value="${cat.id}" data-type="income" style="display: none;">${cat.icon || '📁'} ${cat.name}</option>`;
        });

        accounts.forEach(acc => {
            accountSelect.innerHTML += `<option value="${acc.id}">🏦 ${acc.name} (${window.App.formatMoney(acc.balance)} ₽)</option>`;
        });

        document.getElementById('transDate').valueAsDate = new Date();

        // Фильтрация категорий по типу транзакции
        document.getElementById('transType').addEventListener('change', (e) => {
            const selectedType = e.target.value;
            const options = categorySelect.options;
            for (let option of options) {
                const optionType = option.dataset.type;
                option.style.display = optionType === selectedType ? '' : 'none';
            }
            // Выбираем первую видимую категорию
            for (let option of options) {
                if (option.style.display !== 'none') {
                    categorySelect.value = option.value;
                    break;
                }
            }
        });

        // Триггерим изменение типа
        document.getElementById('transType').dispatchEvent(new Event('change'));

        // Обработка формы
        document.getElementById('transactionForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            await createNewTransaction();
        });

        // Фильтры
        document.getElementById('applyFilters').addEventListener('click', () => {
            transactionsFilters = {
                type: document.getElementById('filterType').value,
                category_id: document.getElementById('filterCategory').value,
                account_id: document.getElementById('filterAccount').value,
                date_from: document.getElementById('filterDateFrom').value,
                date_to: document.getElementById('filterDateTo').value
            };
            currentTransactionsPage = 1;
            loadTransactions(container);
        });

        document.getElementById('resetFilters').addEventListener('click', () => {
            transactionsFilters = {};
            currentTransactionsPage = 1;
            document.getElementById('filterType').value = '';
            document.getElementById('filterCategory').value = '';
            document.getElementById('filterAccount').value = '';
            document.getElementById('filterDateFrom').value = '';
            document.getElementById('filterDateTo').value = '';
            loadTransactions(container);
        });

    } catch (error) {
        console.error('Error loading transactions:', error);
        container.innerHTML = `
            <div class="error">
                ❌ Ошибка загрузки транзакций<br>
                <small>${error.message || 'Проверьте подключение к серверу'}</small>
            </div>
        `;
    }
}

function renderTransactionsTable(transactions) {
    if (!transactions || transactions.length === 0) {
        return '<div class="empty-state"><div class="empty-icon">💸</div><h4>Нет транзакций</h4><p>Создайте первую транзакцию с помощью формы выше</p></div>';
    }

    return `
        <table>
            <thead>
                <tr><th>Дата</th><th>Тип</th><th>Категория</th><th>Счет</th><th>Сумма</th><th>Действия</th></tr>
            </thead>
            <tbody>
                ${transactions.map(t => `
                    <tr>
                        <td>${window.App.formatDate(t.date)}</td>
                        <td>${t.type === 'income' ? '📈 Доход' : '📉 Расход'}</td>
                        <td><span class="category-badge ${t.type}">${t.category?.icon || '📁'} ${t.category?.name || '-'}</span></td>
                        <td>🏦 ${t.account?.name || '-'}</td>
                        <td class="${t.type === 'income' ? 'text-success' : 'text-danger'}">
                            ${t.type === 'income' ? '+' : '-'}${window.App.formatMoney(t.amount)} ₽
                        </td>
                        <td>
                            <button class="delete-transaction btn-icon" data-id="${t.id}" style="background:none; border:none; cursor:pointer; color:#ef4444; font-size:18px;" title="Удалить">🗑️</button>
                        </td>
                    </tr>
                `).join('')}
            </tbody>
        </table>
    `;
}

function renderTransactionsPagination(pagination) {
    if (!pagination || pagination.last_page <= 1) return '';

    let html = '<div class="pagination">';

    if (pagination.current_page > 1) {
        html += `<button class="page-btn" data-page="${pagination.current_page - 1}">← Назад</button>`;
    }

    html += `<span style="margin: 0 15px;">Страница ${pagination.current_page} из ${pagination.last_page}</span>`;

    if (pagination.current_page < pagination.last_page) {
        html += `<button class="page-btn" data-page="${pagination.current_page + 1}">Вперед →</button>`;
    }

    html += '</div>';

    // Добавляем обработчики после рендера
    setTimeout(() => {
        document.querySelectorAll('.page-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                currentTransactionsPage = parseInt(btn.dataset.page);
                loadTransactions(document.getElementById('pageContent'));
            });
        });

        document.querySelectorAll('.delete-transaction').forEach(btn => {
            btn.addEventListener('click', async (e) => {
                if (confirm('Удалить эту транзакцию?')) {
                    try {
                        await window.App.api.deleteTransaction(btn.dataset.id);
                        window.App.showNotification('✅ Транзакция удалена', 'success');
                        await loadTransactions(document.getElementById('pageContent'));
                    } catch (error) {
                        window.App.showNotification('❌ Ошибка при удалении', 'error');
                    }
                }
            });
        });
    }, 100);

    return html;
}

async function createNewTransaction() {
    const data = {
        type: document.getElementById('transType').value,
        category_id: document.getElementById('transCategory').value,
        account_id: document.getElementById('transAccount').value,
        amount: document.getElementById('transAmount').value,
        date: document.getElementById('transDate').value,
        description: document.getElementById('transDescription').value,
        note: document.getElementById('transNote').value
    };

    if (!data.amount || data.amount <= 0) {
        window.App.showNotification('❌ Введите корректную сумму', 'error');
        return;
    }

    try {
        await window.App.api.createTransaction(data);
        window.App.showNotification('✅ Транзакция создана', 'success');

        // Очищаем форму
        document.getElementById('transactionForm').reset();
        document.getElementById('transDate').valueAsDate = new Date();
        document.getElementById('transAmount').value = '';
        document.getElementById('transDescription').value = '';
        document.getElementById('transNote').value = '';

        // Перезагружаем страницу
        await loadTransactions(document.getElementById('pageContent'));
    } catch (error) {
        const message = error.response?.data?.message || 'Ошибка создания транзакции';
        window.App.showNotification(`❌ ${message}`, 'error');
    }
}