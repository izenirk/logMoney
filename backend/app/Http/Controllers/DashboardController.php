<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Models\Account;
use App\Models\Budget;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /**
     * Получение всех данных для дашборда
     */
    public function index(Request $request)
    {
        try {
            $user = $request->user();
            $today = now()->toDateString();
            $currentMonth = now()->month;
            $currentYear = now()->year;

            // 1. Общий баланс по всем счетам (с проверкой на null)
            $totalBalance = Account::where('user_id', $user->id)->sum('balance') ?? 0;

            // 2. Доходы и расходы за сегодня
            $todayIncome = Transaction::where('user_id', $user->id)
                ->where('type', 'income')
                ->whereDate('date', $today)
                ->sum('amount') ?? 0;

            $todayExpense = Transaction::where('user_id', $user->id)
                ->where('type', 'expense')
                ->whereDate('date', $today)
                ->sum('amount') ?? 0;

            // 3. Доходы и расходы за текущий месяц
            $monthIncome = Transaction::where('user_id', $user->id)
                ->where('type', 'income')
                ->whereMonth('date', $currentMonth)
                ->whereYear('date', $currentYear)
                ->sum('amount') ?? 0;

            $monthExpense = Transaction::where('user_id', $user->id)
                ->where('type', 'expense')
                ->whereMonth('date', $currentMonth)
                ->whereYear('date', $currentYear)
                ->sum('amount') ?? 0;

            // 4. Расходы по категориям за текущий месяц
            $expensesByCategory = Transaction::where('user_id', $user->id)
                ->where('type', 'expense')
                ->whereMonth('date', $currentMonth)
                ->whereYear('date', $currentYear)
                ->join('categories', 'transactions.category_id', '=', 'categories.id')
                ->select('categories.id', 'categories.name', 'categories.icon', DB::raw('SUM(transactions.amount) as total'))
                ->groupBy('categories.id', 'categories.name', 'categories.icon')
                ->orderBy('total', 'desc')
                ->get();

            // 5. Доходы по категориям за текущий месяц
            $incomesByCategory = Transaction::where('user_id', $user->id)
                ->where('type', 'income')
                ->whereMonth('date', $currentMonth)
                ->whereYear('date', $currentYear)
                ->join('categories', 'transactions.category_id', '=', 'categories.id')
                ->select('categories.id', 'categories.name', 'categories.icon', DB::raw('SUM(transactions.amount) as total'))
                ->groupBy('categories.id', 'categories.name', 'categories.icon')
                ->orderBy('total', 'desc')
                ->get();

            // 6. Динамика доходов/расходов за последние 6 месяцев
            $monthlyStats = [];
            for ($i = 5; $i >= 0; $i--) {
                $month = now()->subMonths($i);
                $income = Transaction::where('user_id', $user->id)
                    ->where('type', 'income')
                    ->whereYear('date', $month->year)
                    ->whereMonth('date', $month->month)
                    ->sum('amount') ?? 0;

                $expense = Transaction::where('user_id', $user->id)
                    ->where('type', 'expense')
                    ->whereYear('date', $month->year)
                    ->whereMonth('date', $month->month)
                    ->sum('amount') ?? 0;

                $monthlyStats[] = [
                    'month' => $month->format('M Y'),
                    'income' => $income,
                    'expense' => $expense
                ];
            }

            // 7. Последние 10 транзакций
            $recentTransactions = Transaction::where('user_id', $user->id)
                ->with(['category', 'account'])
                ->orderBy('date', 'desc')
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get();

            // 8. Прогресс бюджетов (только если есть бюджеты)
            $budgetProgress = [];
            $budgets = Budget::where('user_id', $user->id)
                ->where('month', $currentMonth)
                ->where('year', $currentYear)
                ->with('category')
                ->get();

            foreach ($budgets as $budget) {
                $spent = Transaction::where('user_id', $user->id)
                    ->where('type', 'expense')
                    ->where('category_id', $budget->category_id)
                    ->whereMonth('date', $currentMonth)
                    ->whereYear('date', $currentYear)
                    ->sum('amount') ?? 0;

                $budgetProgress[] = [
                    'budget' => $budget,
                    'spent' => $spent,
                    'remaining' => $budget->limit_amount - $spent,
                    'percentage' => $spent > 0 ? min(100, ($spent / $budget->limit_amount) * 100) : 0
                ];
            }

            // Формируем ответ с проверкой на null
            return response()->json([
                'success' => true,
                'data' => [
                    'total_balance' => $totalBalance,
                    'today' => [
                        'income' => $todayIncome,
                        'expense' => $todayExpense
                    ],
                    'current_month' => [
                        'income' => $monthIncome,
                        'expense' => $monthExpense,
                        'balance' => $monthIncome - $monthExpense
                    ],
                    'expenses_by_category' => $expensesByCategory ?: [],
                    'incomes_by_category' => $incomesByCategory ?: [],
                    'monthly_stats' => $monthlyStats ?: [],
                    'recent_transactions' => $recentTransactions ?: [],
                    'budget_progress' => $budgetProgress ?: []
                ]
            ]);

        } catch (\Exception $e) {
            // Логируем ошибку для отладки
            \Log::error('Dashboard error: ' . $e->getMessage());
            \Log::error($e->getTraceAsString());

            return response()->json([
                'success' => false,
                'message' => 'Ошибка при загрузке дашборда: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Получение статистики за определенный период
     */
    public function getStatsByPeriod(Request $request)
    {
        try {
            $user = $request->user();
            $period = $request->get('period', 'month');

            $startDate = now();
            $endDate = now();

            switch ($period) {
                case 'week':
                    $startDate = now()->startOfWeek();
                    $endDate = now()->endOfWeek();
                    break;
                case 'month':
                    $startDate = now()->startOfMonth();
                    $endDate = now()->endOfMonth();
                    break;
                case 'year':
                    $startDate = now()->startOfYear();
                    $endDate = now()->endOfYear();
                    break;
            }

            $income = Transaction::where('user_id', $user->id)
                ->where('type', 'income')
                ->whereBetween('date', [$startDate, $endDate])
                ->sum('amount') ?? 0;

            $expense = Transaction::where('user_id', $user->id)
                ->where('type', 'expense')
                ->whereBetween('date', [$startDate, $endDate])
                ->sum('amount') ?? 0;

            return response()->json([
                'success' => true,
                'data' => [
                    'period' => $period,
                    'start_date' => $startDate->toDateString(),
                    'end_date' => $endDate->toDateString(),
                    'income' => $income,
                    'expense' => $expense,
                    'balance' => $income - $expense
                ]
            ]);

        } catch (\Exception $e) {
            \Log::error('Stats error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Произошла ошибка'
            ], 500);
        }
    }
}
