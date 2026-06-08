<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\AccountController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\BudgetController;
use Illuminate\Support\Facades\Route;



Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);


Route::middleware('auth:sanctum')->group(function () {

    // Аутентификация
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);

    // Дашборд и статистика
    Route::get('/dashboard', [DashboardController::class, 'index']);
    Route::get('/dashboard/stats', [DashboardController::class, 'getStatsByPeriod']);

    // Транзакции
    Route::apiResource('transactions', TransactionController::class);
    Route::delete('/transactions/bulk', [TransactionController::class, 'bulkDelete']);

    // Счета
    Route::apiResource('accounts', AccountController::class);
    Route::get('/accounts/balance/total', [AccountController::class, 'getTotalBalance']);

    // Категории
    Route::apiResource('categories', CategoryController::class);

    // Бюджеты
    Route::apiResource('budgets', BudgetController::class);
    Route::get('/budgets/recommendations', [BudgetController::class, 'getRecommendations']);
});
