/**
 * Страница категорий - управление категориями доходов и расходов
 */

export async function loadCategories(container) {
    try {
        container.innerHTML = '<div class="loading">Загрузка...</div>';

        const response = await window.App.api.getCategories();
        const categories = response.data || [];

        const incomeCategories = categories.filter(c => c.type === 'income');
        const expenseCategories = categories.filter(c => c.type === 'expense');

        container.innerHTML = `
            <!-- Кнопка добавления категории -->
            <div style="margin-bottom: 20px; display: flex; justify-content: flex-end;">
                <button class="btn-primary" id="showAddCategoryModalBtn">
                    ➕ Добавить категорию
                </button>
            </div>
            
            <!-- Категории доходов -->
            <div class="form-card">
                <h3>📈 Категории доходов</h3>
                <div id="incomeCategoriesList">
                    ${renderCategoriesGrid(incomeCategories, 'income')}
                </div>
            </div>
            
            <!-- Категории расходов -->
            <div class="form-card">
                <h3>📉 Категории расходов</h3>
                <div id="expenseCategoriesList">
                    ${renderCategoriesGrid(expenseCategories, 'expense')}
                </div>
            </div>
        `;

        // Модальное окно для добавления категории
        const modalContent = `
            <form id="addCategoryForm">
                <div class="form-group">
                    <label class="required">Название категории</label>
                    <input type="text" id="categoryName" placeholder="Продукты, Транспорт..." required>
                </div>
                <div class="form-group">
                    <label class="required">Тип категории</label>
                    <select id="categoryType" required>
                        <option value="expense">📉 Расход</option>
                        <option value="income">📈 Доход</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Иконка (эмодзи)</label>
                    <input type="text" id="categoryIcon" placeholder="🛒" maxlength="2" value="📁">
                </div>
                <div style="display: flex; gap: 10px; margin-top: 20px;">
                    <button type="submit" class="btn-primary">✅ Создать</button>
                    <button type="button" class="btn-secondary" id="closeModalBtn">Отмена</button>
                </div>
            </form>
        `;

        document.getElementById('showAddCategoryModalBtn').addEventListener('click', () => {
            openModal('➕ Новая категория', modalContent);

            document.getElementById('addCategoryForm').addEventListener('submit', async (e) => {
                e.preventDefault();
                await createNewCategory();
            });

            document.getElementById('closeModalBtn').addEventListener('click', () => {
                closeModal();
            });
        });

        // Обработчики для кнопок удаления
        setTimeout(() => {
            document.querySelectorAll('.delete-category').forEach(btn => {
                btn.addEventListener('click', async () => {
                    const id = btn.dataset.id;
                    const name = btn.dataset.name;
                    if (confirm(`Удалить категорию "${name}"? Все транзакции с этой категорией также будут удалены.`)) {
                        try {
                            await App.api.deleteCategory(id);
                            App.showNotification('✅ Категория удалена', 'success');
                            await loadCategories(container);
                        } catch (error) {
                            const message = error.response?.data?.message || 'Ошибка удаления';
                            App.showNotification(`❌ ${message}`, 'error');
                        }
                    }
                });
            });
        }, 100);

    } catch (error) {
        console.error('Error loading categories:', error);
        container.innerHTML = '<div class="error">❌ Ошибка загрузки категорий</div>';
    }
}

function renderCategoriesGrid(categories, type) {
    if (!categories || categories.length === 0) {
        return '<div class="empty-state"><div class="empty-icon">📁</div><h4>Нет категорий</h4><p>Создайте категорию с помощью кнопки выше</p></div>';
    }

    return `
        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 12px;">
            ${categories.map(cat => `
                <div class="category-item" style="display: flex; justify-content: space-between; align-items: center; padding: 12px; background: var(--light-gray); border-radius: var(--radius-sm);">
                    <div>
                        <span style="font-size: 20px; margin-right: 8px;">${cat.icon || '📁'}</span>
                        <strong>${cat.name}</strong>
                        <div style="font-size: 12px; color: var(--gray); margin-top: 4px;">
                            ${type === 'income' ? '📈 Доход' : '📉 Расход'}
                        </div>
                    </div>
                    <button class="delete-category btn-icon" data-id="${cat.id}" data-name="${cat.name}" style="background:none; border:none; cursor:pointer; color:#ef4444; font-size:18px;" title="Удалить">🗑️</button>
                </div>
            `).join('')}
        </div>
    `;
}

async function createNewCategory() {
    const data = {
        name: document.getElementById('categoryName').value,
        type: document.getElementById('categoryType').value,
        icon: document.getElementById('categoryIcon').value || '📁'
    };

    if (!data.name) {
        App.showNotification('❌ Введите название категории', 'error');
        return;
    }

    try {
        await App.api.createCategory(data);
        App.showNotification('✅ Категория создана', 'success');
        closeModal();
        await loadCategories(document.getElementById('pageContent'));
    } catch (error) {
        const message = error.response?.data?.message || 'Ошибка создания категории';
        App.showNotification(`❌ ${message}`, 'error');
    }
}