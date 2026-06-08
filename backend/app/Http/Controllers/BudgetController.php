<?php

namespace App\Http\Controllers;

use App\Models\Budget;
use App\Models\Category;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BudgetController extends Controller
{
    /**
     * Список бюджетов пользователя
     */
    public function index(Request $request)
    {
        try {
            $month = $request->get('month', now()->month);
            $year = $request->get('year', now()->year);

            $budgets = Budget::where('user_id', $request->user()->id)
                ->where('month', $month)
                ->where('year', $year)
                ->with('category')
                ->get();

            // Добавляем информацию о потраченных суммах
            foreach ($budgets as $budget) {
                $spent = Transaction::where('user_id', $request->user()->id)
                    ->where('type', 'expense')
                    ->where('category_id', $budget->category_id)
                    ->whereMonth('date', $month)
                    ->whereYear('date', $year)
                    ->sum('amount');

                $budget->spent = $spent;
                $budget->remaining = $budget->limit_amount - $spent;
                $budget->percentage = $spent > 0 ? min(100, round(($spent / $budget->limit_amount) * 100, 2)) : 0;

                // Статус бюджета
                if ($spent >= $budget->limit_amount) {
                    $budget->status = 'exceeded';
                } elseif ($spent >= $budget->limit_amount * 0.9) {
                    $budget->status = 'warning';
                } else {
                    $budget->status = 'ok';
                }
            }

            // Общая статистика по бюджетам
            $totalBudget = $budgets->sum('limit_amount');
            $totalSpent = $budgets->sum('spent');

            return response()->json([
                'success' => true,
                'data' => $budgets,
                'summary' => [
                    'total_budget' => $totalBudget,
                    'total_spent' => $totalSpent,
                    'total_remaining' => $totalBudget - $totalSpent,
                    'overall_percentage' => $totalBudget > 0 ? round(($totalSpent / $totalBudget) * 100, 2) : 0
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при загрузке бюджетов'
            ], 500);
        }
    }

    /**
     * Создание нового бюджета
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'category_id' => 'required|exists:categories,id',
                'limit_amount' => 'required|numeric|min:0.01',
                'month' => 'required|integer|min:1|max:12',
                'year' => 'required|integer|min:2020|max:2030',
            ]);

            // Проверяем, что категория принадлежит пользователю и она для расходов
            $category = Category::where('id', $validated['category_id'])
                ->where('user_id', $request->user()->id)
                ->first();

            if (!$category) {
                return response()->json([
                    'success' => false,
                    'message' => 'Категория не найдена'
                ], 404);
            }

            if ($category->type !== 'expense') {
                return response()->json([
                    'success' => false,
                    'message' => 'Бюджет можно создать только для категории расходов'
                ], 422);
            }

            // Проверяем, не существует ли уже бюджет для этой категории в указанном месяце
            $existingBudget = Budget::where('user_id', $request->user()->id)
                ->where('category_id', $validated['category_id'])
                ->where('month', $validated['month'])
                ->where('year', $validated['year'])
                ->first();

            if ($existingBudget) {
                return response()->json([
                    'success' => false,
                    'message' => 'Бюджет для этой категории на указанный месяц уже существует'
                ], 422);
            }

            $budget = Budget::create(array_merge($validated, [
                'user_id' => $request->user()->id,
            ]));

            return response()->json([
                'success' => true,
                'data' => $budget->load('category'),
                'message' => 'Бюджет успешно создан'
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при создании бюджета'
            ], 500);
        }
    }

    /**
     * Просмотр конкретного бюджета
     */
    public function show(Request $request, $id)
    {
        try {
            $budget = Budget::where('user_id', $request->user()->id)
                ->with('category')
                ->find($id);

            if (!$budget) {
                return response()->json([
                    'success' => false,
                    'message' => 'Бюджет не найден'
                ], 404);
            }

            // Получаем детальную информацию о тратах по категории
            $transactions = Transaction::where('user_id', $request->user()->id)
                ->where('type', 'expense')
                ->where('category_id', $budget->category_id)
                ->whereMonth('date', $budget->month)
                ->whereYear('date', $budget->year)
                ->orderBy('date', 'desc')
                ->get();

            $spent = $transactions->sum('amount');

            return response()->json([
                'success' => true,
                'data' => [
                    'budget' => $budget,
                    'spent' => $spent,
                    'remaining' => $budget->limit_amount - $spent,
                    'percentage' => $spent > 0 ? min(100, round(($spent / $budget->limit_amount) * 100, 2)) : 0,
                    'transactions' => $transactions
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при загрузке бюджета'
            ], 500);
        }
    }

    /**
     * Обновление бюджета
     */
    public function update(Request $request, $id)
    {
        try {
            $budget = Budget::where('user_id', $request->user()->id)
                ->find($id);

            if (!$budget) {
                return response()->json([
                    'success' => false,
                    'message' => 'Бюджет не найден'
                ], 404);
            }

            $validated = $request->validate([
                'limit_amount' => 'required|numeric|min:0.01',
            ]);

            $budget->update($validated);

            return response()->json([
                'success' => true,
                'data' => $budget,
                'message' => 'Бюджет успешно обновлен'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при обновлении бюджета'
            ], 500);
        }
    }

    /**
     * Удаление бюджета
     */
    public function destroy(Request $request, $id)
    {
        try {
            $budget = Budget::where('user_id', $request->user()->id)
                ->find($id);

            if (!$budget) {
                return response()->json([
                    'success' => false,
                    'message' => 'Бюджет не найден'
                ], 404);
            }

            $budget->delete();

            return response()->json([
                'success' => true,
                'message' => 'Бюджет успешно удален'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при удалении бюджета'
            ], 500);
        }
    }

    /**
     * Получение рекомендаций по бюджету
     */
    public function getRecommendations(Request $request)
    {
        try {
            $user = $request->user();
            $currentMonth = now()->month;
            $currentYear = now()->year;

            // Получаем все категории расходов
            $expenseCategories = Category::where('user_id', $user->id)
                ->where('type', 'expense')
                ->get();

            $recommendations = [];

            foreach ($expenseCategories as $category) {
                // Средние траты за последние 3 месяца
                $avgSpent = Transaction::where('user_id', $user->id)
                    ->where('type', 'expense')
                    ->where('category_id', $category->id)
                    ->whereBetween('date', [now()->subMonths(3)->startOfMonth(), now()->subMonth()->endOfMonth()])
                    ->avg('amount');

                // Траты в текущем месяце
                $currentSpent = Transaction::where('user_id', $user->id)
                    ->where('type', 'expense')
                    ->where('category_id', $category->id)
                    ->whereMonth('date', $currentMonth)
                    ->whereYear('date', $currentYear)
                    ->sum('amount');

                // Рекомендуемый лимит (среднее * 1.1)
                $recommendedLimit = round($avgSpent * 1.1, 2);

                if ($recommendedLimit > 0) {
                    $recommendations[] = [
                        'category_id' => $category->id,
                        'category_name' => $category->name,
                        'average_spent' => round($avgSpent, 2),
                        'current_spent' => $currentSpent,
                        'recommended_limit' => $recommendedLimit,
                        'saving_potential' => round($recommendedLimit - $currentSpent, 2)
                    ];
                }
            }

            return response()->json([
                'success' => true,
                'data' => $recommendations
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при получении рекомендаций'
            ], 500);
        }
    }
}
