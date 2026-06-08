/**
 * Страница бюджетов
 */

let currentMonth = new Date().getMonth() + 1;
let currentYear = new Date().getFullYear();

export async function loadBudgets(container) {
    try {
        container.innerHTML = '<div class="loading">Загрузка...</div>';

        const [budgetsRes, categoriesRes] = await Promise.all([
            window.App.api.getBudgets({ month: currentMonth, year: currentYear }),
            window.App.api.getCategories({ type: 'expense' })
        ]);

        const budgets = budgetsRes?.data || [];
        const categories = categoriesRes?.data || [];
        const summary = budgetsRes?.summary || { total_budget: 0, total_spent: 0, total_remaining: 0 };

        container.innerHTML = `
            <div class="form-card">
                <div class="form-row">
                    <div class="form-group">
                        <label>Месяц</label>
                        <select id="budgetMonth">
                            <option value="1">Январь</option>
                            <option value="2">Февраль</option>
                            <option value="3">Март</option>
                            <option value="4">Апрель</option>
                            <option value="5">Май</option>
                            <option value="6">Июнь</option>
                            <option value="7">Июль</option>
                            <option value="8">Август</option>
                            <option value="9">Сентябрь</option>
                            <option value="10">Октябрь</option>
                            <option value="11">Ноябрь</option>
                            <option value="12">Декабрь</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Год</label>
                        <select id="budgetYear">
                            ${generateYearOptions()}
                        </select>
                    </div>
                    <div class="form-group" style="display: flex; align-items: flex-end;">
                        <button class="btn-primary" id="applyMonthBtn">📅 Применить</button>
                    </div>
                </div>
            </div>
            
            <div style="margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center;">
                <h3>🎯 Бюджеты на ${getMonthName(currentMonth)} ${currentYear}</h3>
                ${categories.length > 0 ? `
                    <button class="btn-primary" id="showAddBudgetModalBtn">
                        ➕ Добавить бюджет
                    </button>
                ` : ''}
            </div>
            
            <div class="stats-grid" style="margin-bottom: 24px;">
                <div class="stat-card">
                    <div class="stat-card-header">
                        <span class="stat-card-title">Общий бюджет</span>
                        <span class="stat-card-icon">💰</span>
                    </div>
                    <div class="stat-card-value">${window.App.formatMoney(summary.total_budget)} ₽</div>
                </div>
                <div class="stat-card">
                    <div class="stat-card-header">
                        <span class="stat-card-title">Потрачено</span>
                        <span class="stat-card-icon">📉</span>
                    </div>
                    <div class="stat-card-value text-danger">${window.App.formatMoney(summary.total_spent)} ₽</div>
                </div>
                <div class="stat-card">
                    <div class="stat-card-header">
                        <span class="stat-card-title">Осталось</span>
                        <span class="stat-card-icon">💚</span>
                    </div>
                    <div class="stat-card-value text-success">${window.App.formatMoney(summary.total_remaining)} ₽</div>
                </div>
            </div>
            
            <div class="table-container">
                <h3>📊 Мои бюджеты</h3>
                <div id="budgetsList">
                    ${renderBudgetsTable(budgets)}
                </div>
            </div>
        `;

        // Устанавливаем текущие значения
        document.getElementById('budgetMonth').value = currentMonth;
        document.getElementById('budgetYear').value = currentYear;

        // Обработчик смены месяца
        document.getElementById('applyMonthBtn').addEventListener('click', () => {
            currentMonth = parseInt(document.getElementById('budgetMonth').value);
            currentYear = parseInt(document.getElementById('budgetYear').value);
            loadBudgets(container);
        });

        // Модальное окно для добавления бюджета (только если есть категории)
        if (categories.length > 0) {
            const modalContent = `
                <form id="addBudgetForm">
                    <div class="form-group">
                        <label class="required">Категория</label>
                        <select id="budgetCategory" required>
                            <option value="">Выберите категорию</option>
                            ${categories.map(c => `<option value="${c.id}">${c.icon || '📁'} ${c.name}</option>`).join('')}
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="required">Лимит (₽)</label>
                        <input type="number" id="budgetLimit" step="0.01" placeholder="10000" required>
                    </div>
                    <div style="display: flex; gap: 10px; margin-top: 20px;">
                        <button type="submit" class="btn-primary">✅ Создать</button>
                        <button type="button" class="btn-secondary" id="closeModalBtn">Отмена</button>
                    </div>
                </form>
            `;

            document.getElementById('showAddBudgetModalBtn').addEventListener('click', () => {
                window.openModal('➕ Новый бюджет', modalContent);

                document.getElementById('addBudgetForm').addEventListener('submit', async (e) => {
                    e.preventDefault();
                    await createNewBudget();
                });

                document.getElementById('closeModalBtn').addEventListener('click', () => {
                    window.closeModal();
                });
            });
        }

        // Обработчики для кнопок удаления
        setTimeout(() => {
            document.querySelectorAll('.delete-budget').forEach(btn => {
                btn.addEventListener('click', async () => {
                    const id = btn.dataset.id;
                    if (confirm('Удалить этот бюджет?')) {
                        try {
                            await window.App.api.deleteBudget(id);
                            window.App.showNotification('✅ Бюджет удален', 'success');
                            await loadBudgets(container);
                        } catch (error) {
                            window.App.showNotification('❌ Ошибка удаления', 'error');
                        }
                    }
                });
            });
        }, 100);

    } catch (error) {
        console.error('Error loading budgets:', error);
        container.innerHTML = `
            <div class="error">
                ❌ Ошибка загрузки бюджетов<br>
                <small>${error.message || 'Проверьте подключение к серверу'}</small>
            </div>
        `;
    }
}

function renderBudgetsTable(budgets) {
    if (!budgets || budgets.length === 0) {
        return '<div class="empty-state"><div class="empty-icon">🎯</div><h4>Нет бюджетов</h4><p>Создайте бюджет для категории расходов</p></div>';
    }

    return `
        <table>
            <thead>
                <tr><th>Категория</th><th>Лимит</th><th>Потрачено</th><th>Осталось</th><th>Прогресс</th><th>Действия</th></tr>
            </thead>
            <tbody>
                ${budgets.map(b => `
                    <tr>
                        <td><span class="category-badge expense">${b.category?.icon || '📁'} ${b.category?.name}</span></td>
                        <td>${window.App.formatMoney(b.limit_amount)} ₽</td>
                        <td class="text-danger">${window.App.formatMoney(b.spent)} ₽</td>
                        <td class="${b.remaining >= 0 ? 'text-success' : 'text-danger'}">${window.App.formatMoney(b.remaining)} ₽</td>
                        <td>
                            <div class="progress-bar">
                                <div class="progress-fill ${b.percentage >= 90 ? 'warning' : ''} ${b.percentage >= 100 ? 'danger' : ''}" 
                                     style="width: ${b.percentage}%"></div>
                            </div>
                            <div class="progress-stats">${b.percentage}%</div>
                        </td>
                        <td>
                            <button class="delete-budget btn-icon" data-id="${b.id}" style="background:none; border:none; cursor:pointer; color:#ef4444; font-size:18px;" title="Удалить">🗑️</button>
                         </td>
                    </tr>
                `).join('')}
            </tbody>
        </table>
    `;
}

function generateYearOptions() {
    const currentYear = new Date().getFullYear();
    let options = '';
    for (let year = currentYear - 2; year <= currentYear + 2; year++) {
        options += `<option value="${year}" ${year === currentYear ? 'selected' : ''}>${year}</option>`;
    }
    return options;
}

function getMonthName(month) {
    const months = ['Январь', 'Февраль', 'Март', 'Апрель', 'Май', 'Июнь', 'Июль', 'Август', 'Сентябрь', 'Октябрь', 'Ноябрь', 'Декабрь'];
    return months[month - 1];
}

async function createNewBudget() {
    const data = {
        category_id: document.getElementById('budgetCategory').value,
        limit_amount: document.getElementById('budgetLimit').value,
        month: currentMonth,
        year: currentYear
    };

    if (!data.category_id) {
        window.App.showNotification('❌ Выберите категорию', 'error');
        return;
    }

    if (!data.limit_amount || data.limit_amount <= 0) {
        window.App.showNotification('❌ Введите корректный лимит', 'error');
        return;
    }

    try {
        await window.App.api.createBudget(data);
        window.App.showNotification('✅ Бюджет создан', 'success');
        window.closeModal();
        await loadBudgets(document.getElementById('pageContent'));
    } catch (error) {
        const message = error.response?.data?.message || 'Ошибка создания бюджета';
        window.App.showNotification(`❌ ${message}`, 'error');
    }
}