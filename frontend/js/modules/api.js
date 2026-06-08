/**
 * API клиент для работы с бэкендом
 */

export class API {
    constructor() {
        this.baseURL = 'http://localhost:8000/api';
        this.initAxios();
    }

    initAxios() {
        axios.defaults.baseURL = this.baseURL;
        axios.defaults.withCredentials = true;
        axios.defaults.withXSRFToken = true;
        axios.defaults.headers.common['Accept'] = 'application/json';
        axios.defaults.headers.common['Content-Type'] = 'application/json';

        // Добавляем интерсептор для обработки ошибок
        axios.interceptors.response.use(
            response => response,
            error => {
                if (error.response?.status === 419) {
                    console.error('CSRF token mismatch');
                    if (App && App.showNotification) {
                        App.showNotification('Ошибка сессии. Обновите страницу.', 'error');
                    }
                } else if (error.response?.status === 401) {
                    console.error('Unauthorized');
                    if (App && App.currentUser) {
                        showAuth();
                        App.showNotification('Сессия истекла. Войдите снова.', 'error');
                    }
                }
                return Promise.reject(error);
            }
        );
    }

    // Auth endpoints
    async getCsrfCookie() {
        await axios.get('http://localhost:8000/sanctum/csrf-cookie');
    }

    async register(data) {
        const response = await axios.post('/register', data);
        return response.data;
    }

    async login(data) {
        const response = await axios.post('/login', data);
        return response.data;
    }

    async logout() {
        const response = await axios.post('/logout');
        return response.data;
    }

    async getUser() {
        const response = await axios.get('/user');
        return response.data;
    }

    // Dashboard
    async getDashboard() {
        const response = await axios.get('/dashboard');
        return response.data;
    }

    // Transactions
    async getTransactions(params = {}) {
        const response = await axios.get('/transactions', { params });
        return response.data;
    }

    async createTransaction(data) {
        const response = await axios.post('/transactions', data);
        return response.data;
    }

    async updateTransaction(id, data) {
        const response = await axios.put(`/transactions/${id}`, data);
        return response.data;
    }

    async deleteTransaction(id) {
        const response = await axios.delete(`/transactions/${id}`);
        return response.data;
    }

    async bulkDeleteTransactions(ids) {
        const response = await axios.delete('/transactions/bulk', { data: { ids } });
        return response.data;
    }

    // Accounts
    async getAccounts() {
        const response = await axios.get('/accounts');
        return response.data;
    }

    async getAccount(id) {
        const response = await axios.get(`/accounts/${id}`);
        return response.data;
    }

    async createAccount(data) {
        const response = await axios.post('/accounts', data);
        return response.data;
    }

    async updateAccount(id, data) {
        const response = await axios.put(`/accounts/${id}`, data);
        return response.data;
    }

    async deleteAccount(id) {
        const response = await axios.delete(`/accounts/${id}`);
        return response.data;
    }

    async getTotalBalance() {
        const response = await axios.get('/accounts/balance/total');
        return response.data;
    }

    // Categories
    async getCategories(params = {}) {
        const response = await axios.get('/categories', { params });
        return response.data;
    }

    async createCategory(data) {
        const response = await axios.post('/categories', data);
        return response.data;
    }

    async updateCategory(id, data) {
        const response = await axios.put(`/categories/${id}`, data);
        return response.data;
    }

    async deleteCategory(id) {
        const response = await axios.delete(`/categories/${id}`);
        return response.data;
    }

    // Budgets
    async getBudgets(params = {}) {
        const response = await axios.get('/budgets', { params });
        return response.data;
    }

    async createBudget(data) {
        const response = await axios.post('/budgets', data);
        return response.data;
    }

    async updateBudget(id, data) {
        const response = await axios.put(`/budgets/${id}`, data);
        return response.data;
    }

    async deleteBudget(id) {
        const response = await axios.delete(`/budgets/${id}`);
        return response.data;
    }

    async getBudgetRecommendations() {
        const response = await axios.get('/budgets/recommendations');
        return response.data;
    }
}