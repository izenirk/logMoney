<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CategoryController extends Controller
{
    /**
     * Список всех категорий пользователя
     */
    public function index(Request $request)
    {
        try {
            $type = $request->get('type');

            $query = Category::where('user_id', $request->user()->id);

            if ($type && in_array($type, ['income', 'expense'])) {
                $query->where('type', $type);
            }

            $categories = $query->orderBy('type')
                ->orderBy('name')
                ->get();

            // Добавляем статистику по каждой категории
            foreach ($categories as $category) {
                $category->total_spent = Transaction::where('category_id', $category->id)
                    ->where('type', $category->type)
                    ->whereMonth('date', now()->month)
                    ->sum('amount');

                $category->transaction_count = Transaction::where('category_id', $category->id)
                    ->whereMonth('date', now()->month)
                    ->count();
            }

            return response()->json([
                'success' => true,
                'data' => $categories
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при загрузке категорий'
            ], 500);
        }
    }

    /**
     * Создание новой категории
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255|unique:categories,name,NULL,id,user_id,' . $request->user()->id,
                'type' => 'required|in:income,expense',
                'icon' => 'nullable|string|max:10',
            ]);

            $category = Category::create(array_merge($validated, [
                'user_id' => $request->user()->id,
            ]));

            return response()->json([
                'success' => true,
                'data' => $category,
                'message' => 'Категория успешно создана'
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при создании категории'
            ], 500);
        }
    }

    /**
     * Просмотр конкретной категории
     */
    public function show(Request $request, $id)
    {
        try {
            $category = Category::where('user_id', $request->user()->id)
                ->find($id);

            if (!$category) {
                return response()->json([
                    'success' => false,
                    'message' => 'Категория не найдена'
                ], 404);
            }

            // Получаем статистику по категории
            $stats = [
                'total_income' => Transaction::where('category_id', $id)
                    ->where('type', 'income')
                    ->sum('amount'),
                'total_expense' => Transaction::where('category_id', $id)
                    ->where('type', 'expense')
                    ->sum('amount'),
                'current_month' => Transaction::where('category_id', $id)
                    ->whereMonth('date', now()->month)
                    ->sum('amount'),
                'transaction_count' => Transaction::where('category_id', $id)->count(),
                'last_transaction' => Transaction::where('category_id', $id)
                    ->orderBy('date', 'desc')
                    ->first()
            ];

            return response()->json([
                'success' => true,
                'data' => [
                    'category' => $category,
                    'stats' => $stats
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при загрузке категории'
            ], 500);
        }
    }

    /**
     * Обновление категории
     */
    public function update(Request $request, $id)
    {
        try {
            $category = Category::where('user_id', $request->user()->id)
                ->find($id);

            if (!$category) {
                return response()->json([
                    'success' => false,
                    'message' => 'Категория не найдена'
                ], 404);
            }

            $validated = $request->validate([
                'name' => 'sometimes|string|max:255|unique:categories,name,' . $id . ',id,user_id,' . $request->user()->id,
                'icon' => 'nullable|string|max:10',
            ]);

            $category->update($validated);

            return response()->json([
                'success' => true,
                'data' => $category,
                'message' => 'Категория успешно обновлена'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при обновлении категории'
            ], 500);
        }
    }

    /**
     * Удаление категории
     */
    public function destroy(Request $request, $id)
    {
        try {
            $category = Category::where('user_id', $request->user()->id)
                ->find($id);

            if (!$category) {
                return response()->json([
                    'success' => false,
                    'message' => 'Категория не найдена'
                ], 404);
            }

            // Проверяем, есть ли транзакции в этой категории
            $transactionsCount = Transaction::where('category_id', $id)->count();

            if ($transactionsCount > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Невозможно удалить категорию, в которой есть транзакции. Сначала удалите все транзакции этой категории.'
                ], 422);
            }

            $category->delete();

            return response()->json([
                'success' => true,
                'message' => 'Категория успешно удалена'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при удалении категории'
            ], 500);
        }
    }
}
