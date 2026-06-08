<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AccountController extends Controller
{
    /**
     * Список всех счетов пользователя
     */
    public function index(Request $request)
    {
        try {
            $accounts = Account::where('user_id', $request->user()->id)
                ->orderBy('created_at', 'asc')
                ->get();

            // Добавляем общую сумму
            $totalBalance = $accounts->sum('balance');

            return response()->json([
                'success' => true,
                'data' => $accounts,
                'total_balance' => $totalBalance
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при загрузке счетов'
            ], 500);
        }
    }

    /**
     * Создание нового счета
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'type' => 'required|in:cash,card,bank,electronic',
                'balance' => 'required|numeric|min:0',
                'currency' => 'required|string|size:3|in:RUB,USD,EUR',
            ]);

            $account = Account::create(array_merge($validated, [
                'user_id' => $request->user()->id,
            ]));

            return response()->json([
                'success' => true,
                'data' => $account,
                'message' => 'Счет успешно создан'
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при создании счета'
            ], 500);
        }
    }

    /**
     * Просмотр конкретного счета
     */
    public function show(Request $request, $id)
    {
        try {
            $account = Account::where('user_id', $request->user()->id)
                ->find($id);

            if (!$account) {
                return response()->json([
                    'success' => false,
                    'message' => 'Счет не найден'
                ], 404);
            }

            // Получаем последние транзакции по счету
            $recentTransactions = Transaction::where('user_id', $request->user()->id)
                ->where('account_id', $id)
                ->with(['category'])
                ->orderBy('date', 'desc')
                ->limit(10)
                ->get();

            // Статистика по счету
            $totalIncome = Transaction::where('user_id', $request->user()->id)
                ->where('account_id', $id)
                ->where('type', 'income')
                ->sum('amount');

            $totalExpense = Transaction::where('user_id', $request->user()->id)
                ->where('account_id', $id)
                ->where('type', 'expense')
                ->sum('amount');

            return response()->json([
                'success' => true,
                'data' => [
                    'account' => $account,
                    'recent_transactions' => $recentTransactions,
                    'stats' => [
                        'total_income' => $totalIncome,
                        'total_expense' => $totalExpense,
                        'balance_change' => $totalIncome - $totalExpense
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при загрузке счета'
            ], 500);
        }
    }

    /**
     * Обновление счета
     */
    public function update(Request $request, $id)
    {
        try {
            $account = Account::where('user_id', $request->user()->id)
                ->find($id);

            if (!$account) {
                return response()->json([
                    'success' => false,
                    'message' => 'Счет не найден'
                ], 404);
            }

            $validated = $request->validate([
                'name' => 'sometimes|string|max:255',
                'type' => 'sometimes|in:cash,card,bank,electronic',
                'currency' => 'sometimes|string|size:3|in:RUB,USD,EUR',
            ]);

            $account->update($validated);

            return response()->json([
                'success' => true,
                'data' => $account,
                'message' => 'Счет успешно обновлен'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при обновлении счета'
            ], 500);
        }
    }

    /**
     * Удаление счета
     */
    public function destroy(Request $request, $id)
    {
        try {
            $account = Account::where('user_id', $request->user()->id)
                ->find($id);

            if (!$account) {
                return response()->json([
                    'success' => false,
                    'message' => 'Счет не найден'
                ], 404);
            }

            // Проверяем, есть ли транзакции на этом счете
            $transactionsCount = Transaction::where('account_id', $id)->count();

            if ($transactionsCount > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Невозможно удалить счет, на котором есть транзакции. Сначала удалите все транзакции по этому счету.'
                ], 422);
            }

            $account->delete();

            return response()->json([
                'success' => true,
                'message' => 'Счет успешно удален'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при удалении счета'
            ], 500);
        }
    }

    /**
     * Получение баланса по всем счетам
     */
    public function getTotalBalance(Request $request)
    {
        try {
            $totalBalance = Account::where('user_id', $request->user()->id)->sum('balance');

            $accountsByType = Account::where('user_id', $request->user()->id)
                ->select('type', DB::raw('SUM(balance) as total'))
                ->groupBy('type')
                ->get();

            return response()->json([
                'success' => true,
                'total_balance' => $totalBalance,
                'by_type' => $accountsByType
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при загрузке баланса'
            ], 500);
        }
    }
}
