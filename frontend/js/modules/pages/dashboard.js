/**
 * Страница дашборда
 */

export async function loadDashboard(container) {
    try {
        container.innerHTML = '<div class="loading">Загрузка данных...</div>';

        const response = await window.App.api.getDashboard();

        if (!response || !response.success) {
            container.innerHTML = `
                <div class="error">
                    ❌ Ошибка загрузки данных<br>
                    <small>${response?.message || 'Попробуйте обновить страницу'}</small>
                </div>
            `;
            return;
        }

        const data = response.data;

        // Проверяем наличие данных и подставляем значения по умолчанию
        const totalBalance = data.total_balance || 0;
        const todayIncome = data.today?.income || 0;
        const todayExpense = data.today?.expense || 0;
        const monthIncome = data.current_month?.income || 0;
        const monthExpense = data.current_month?.expense || 0;
        const monthBalance = data.current_month?.balance || 0;
        const expensesByCategory = data.expenses_by_category || [];
        const incomesByCategory = data.incomes_by_category || [];
        const recentTransactions = data.recent_transactions || [];

        container.innerHTML = `
            <div class="stats-grid">
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
                        <span class="stat-card-title">Доходы сегодня</span>
                        <span class="stat-card-icon">📈</span>
                    </div>
                    <div class="stat-card-value text-success">+${window.App.formatMoney(todayIncome)} ₽</div>
                    <div class="stat-card-change positive">За сегодня</div>
                </div>
                <div class="stat-card">
                    <div class="stat-card-header">
                        <span class="stat-card-title">Расходы сегодня</span>
                        <span class="stat-card-icon">📉</span>
                    </div>
                    <div class="stat-card-value text-danger">-${window.App.formatMoney(todayExpense)} ₽</div>
                    <div class="stat-card-change negative">За сегодня</div>
                </div>
                <div class="stat-card">
                    <div class="stat-card-header">
                        <span class="stat-card-title">Баланс месяца</span>
                        <span class="stat-card-icon">📊</span>
                    </div>
                    <div class="stat-card-value ${monthBalance >= 0 ? 'text-success' : 'text-danger'}">
                        ${monthBalance >= 0 ? '+' : ''}${window.App.formatMoney(monthBalance)} ₽
                    </div>
                    <div class="stat-card-change">Доходы: +${window.App.formatMoney(monthIncome)} ₽ | Расходы: -${window.App.formatMoney(monthExpense)} ₽</div>
                </div>
            </div>
            
            ${expensesByCategory.length > 0 || incomesByCategory.length > 0 ? `
            <div class="charts-grid">
                ${expensesByCategory.length > 0 ? `
                <div class="chart-card">
                    <h3>📊 Расходы по категориям</h3>
                    <div class="chart-container">
                        <canvas id="expenseChart"></canvas>
                    </div>
                </div>
                ` : ''}
                ${incomesByCategory.length > 0 ? `
                <div class="chart-card">
                    <h3>📈 Доходы по категориям</h3>
                    <div class="chart-container">
                        <canvas id="incomeChart"></canvas>
                    </div>
                </div>
                ` : ''}
            </div>
            ` : '<div class="chart-card"><p class="text-center">📊 Добавьте транзакции для отображения графиков</p></div>'}
            
            <div class="chart-card mt-20">
                <h3>🕒 Последние транзакции</h3>
                <div class="table-container">
                    ${recentTransactions.length > 0 ? `
                    <table>
                        <thead>
                            <tr><th>Дата</th><th>Описание</th><th>Категория</th><th>Счет</th><th>Сумма</th></tr>
                        </thead>
                        <tbody>
                            ${recentTransactions.map(t => `
                                <tr>
                                    <td>${window.App.formatDate(t.date)}</td>
                                    <td>${t.description || '-'}</td>
                                    <td><span class="category-badge ${t.type}">${t.category?.icon || ''} ${t.category?.name || '-'}</span></td>
                                    <td>${t.account?.name || '-'}</td>
                                    <td class="${t.type === 'income' ? 'text-success' : 'text-danger'}">
                                        ${t.type === 'income' ? '+' : '-'}${window.App.formatMoney(t.amount)} ₽
                                    </td>
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                    ` : '<div class="empty-state"><div class="empty-icon">💸</div><h4>Нет транзакций</h4><p>Создайте первую транзакцию в разделе "Транзакции"</p></div>'}
                </div>
            </div>
        `;

        // Создаем графики только если есть данные
        setTimeout(() => {
            if (expensesByCategory.length > 0) {
                const expenseCtx = document.getElementById('expenseChart')?.getContext('2d');
                if (expenseCtx) {
                    new Chart(expenseCtx, {
                        type: 'pie',
                        data: {
                            labels: expensesByCategory.map(c => c.name),
                            datasets: [{
                                data: expensesByCategory.map(c => c.total),
                                backgroundColor: ['#6366f1', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#ec4899', '#06b6d4', '#84cc16']
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: true,
                            plugins: {
                                legend: { position: 'bottom' }
                            }
                        }
                    });
                }
            }

            if (incomesByCategory.length > 0) {
                const incomeCtx = document.getElementById('incomeChart')?.getContext('2d');
                if (incomeCtx) {
                    new Chart(incomeCtx, {
                        type: 'pie',
                        data: {
                            labels: incomesByCategory.map(c => c.name),
                            datasets: [{
                                data: incomesByCategory.map(c => c.total),
                                backgroundColor: ['#10b981', '#6366f1', '#f59e0b', '#8b5cf6', '#06b6d4']
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: true,
                            plugins: {
                                legend: { position: 'bottom' }
                            }
                        }
                    });
                }
            }
        }, 100);

    } catch (error) {
        console.error('Error loading dashboard:', error);
        container.innerHTML = `
            <div class="error">
                ❌ Ошибка загрузки данных<br>
                <small>${error.message || 'Проверьте подключение к серверу'}</small>
            </div>
        `;
    }
}