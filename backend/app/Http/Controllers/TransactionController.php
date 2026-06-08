<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Models\Account;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TransactionController extends Controller
{
    /**
     * Список транзакций с фильтрацией
     */
    public function index(Request $request)
    {
        try {
            $query = Transaction::where('user_id', $request->user()->id)
                ->with(['category', 'account']);

            // Фильтр по типу
            if ($request->has('type') && in_array($request->type, ['income', 'expense'])) {
                $query->where('type', $request->type);
            }

            // Фильтр по категории
            if ($request->has('category_id')) {
                $query->where('category_id', $request->category_id);
            }

            // Фильтр по счету
            if ($request->has('account_id')) {
                $query->where('account_id', $request->account_id);
            }

            // Фильтр по датам
            if ($request->has('date_from')) {
                $query->whereDate('date', '>=', $request->date_from);
            }
            if ($request->has('date_to')) {
                $query->whereDate('date', '<=', $request->date_to);
            }

            // Сортировка
            $sortField = $request->get('sort', 'date');
            $sortOrder = $request->get('order', 'desc');
            $query->orderBy($sortField, $sortOrder);

            $transactions = $query->paginate($request->get('per_page', 20));

            // Добавляем общую сумму
            $totalIncome = $query->clone()->where('type', 'income')->sum('amount') ?? 0;
            $totalExpense = $query->clone()->where('type', 'expense')->sum('amount') ?? 0;

            return response()->json([
                'success' => true,
                'data' => $transactions,
                'meta' => [
                    'total_income' => $totalIncome,
                    'total_expense' => $totalExpense,
                    'balance' => $totalIncome - $totalExpense
                ]
            ]);

        } catch (\Exception $e) {
            \Log::error('Transactions error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Ошибка при загрузке транзакций: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Создание новой транзакции
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'category_id' => 'required|exists:categories,id',
                'account_id' => 'required|exists:accounts,id',
                'type' => 'required|in:income,expense',
                'amount' => 'required|numeric|min:0.01',
                'date' => 'required|date',
                'description' => 'nullable|string|max:255',
                'note' => 'nullable|string|max:1000',
            ]);

            // Проверяем, что категория принадлежит пользователю
            $category = Category::where('id', $validated['category_id'])
                ->where('user_id', $request->user()->id)
                ->first();

            if (!$category) {
                return response()->json([
                    'success' => false,
                    'message' => 'Категория не найдена'
                ], 404);
            }

            // Проверяем, что тип транзакции соответствует типу категории
            if ($category->type !== $validated['type']) {
                return response()->json([
                    'success' => false,
                    'message' => 'Тип транзакции не соответствует типу категории'
                ], 422);
            }

            // Проверяем, что счет принадлежит пользователю
            $account = Account::where('id', $validated['account_id'])
                ->where('user_id', $request->user()->id)
                ->first();

            if (!$account) {
                return response()->json([
                    'success' => false,
                    'message' => 'Счет не найден'
                ], 404);
            }

            DB::beginTransaction();

            // Создаем транзакцию
            $transaction = Transaction::create(array_merge($validated, [
                'user_id' => $request->user()->id,
            ]));

            // Обновляем баланс счета
            if ($validated['type'] === 'income') {
                $account->balance += $validated['amount'];
            } else {
                if ($account->balance < $validated['amount']) {
                    DB::rollBack();
                    return response()->json([
                        'success' => false,
                        'message' => 'Недостаточно средств на счете'
                    ], 422);
                }
                $account->balance -= $validated['amount'];
            }
            $account->save();

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $transaction->load(['category', 'account']),
                'message' => 'Транзакция успешно создана'
            ], 201);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'errors' => $e->errors(),
                'message' => 'Ошибка валидации'
            ], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при создании транзакции: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Просмотр конкретной транзакции
     */
    public function show(Request $request, $id)
    {
        try {
            $transaction = Transaction::where('user_id', $request->user()->id)
                ->with(['category', 'account'])
                ->find($id);

            if (!$transaction) {
                return response()->json([
                    'success' => false,
                    'message' => 'Транзакция не найдена'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $transaction
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при загрузке транзакции'
            ], 500);
        }
    }

    /**
     * Обновление транзакции
     */
    public function update(Request $request, $id)
    {
        try {
            $transaction = Transaction::where('user_id', $request->user()->id)->find($id);

            if (!$transaction) {
                return response()->json([
                    'success' => false,
                    'message' => 'Транзакция не найдена'
                ], 404);
            }

            $validated = $request->validate([
                'category_id' => 'sometimes|exists:categories,id',
                'account_id' => 'sometimes|exists:accounts,id',
                'type' => 'sometimes|in:income,expense',
                'amount' => 'sometimes|numeric|min:0.01',
                'date' => 'sometimes|date',
                'description' => 'nullable|string|max:255',
                'note' => 'nullable|string|max:1000',
            ]);

            DB::beginTransaction();

            // Возвращаем старую сумму на счет
            $oldAccount = Account::find($transaction->account_id);
            if ($transaction->type === 'income') {
                $oldAccount->balance -= $transaction->amount;
            } else {
                $oldAccount->balance += $transaction->amount;
            }
            $oldAccount->save();

            // Обновляем транзакцию
            $transaction->update($validated);

            // Применяем новую сумму к счету
            $newAccount = Account::find($validated['account_id'] ?? $transaction->account_id);
            $newType = $validated['type'] ?? $transaction->type;
            $newAmount = $validated['amount'] ?? $transaction->amount;

            if ($newType === 'income') {
                $newAccount->balance += $newAmount;
            } else {
                if ($newAccount->balance < $newAmount) {
                    DB::rollBack();
                    return response()->json([
                        'success' => false,
                        'message' => 'Недостаточно средств на счете'
                    ], 422);
                }
                $newAccount->balance -= $newAmount;
            }
            $newAccount->save();

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $transaction->fresh(['category', 'account']),
                'message' => 'Транзакция успешно обновлена'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при обновлении транзакции'
            ], 500);
        }
    }

    /**
     * Удаление транзакции
     */
    public function destroy(Request $request, $id)
    {
        try {
            $transaction = Transaction::where('user_id', $request->user()->id)->find($id);

            if (!$transaction) {
                return response()->json([
                    'success' => false,
                    'message' => 'Транзакция не найдена'
                ], 404);
            }

            DB::beginTransaction();

            // Возвращаем сумму на счет
            $account = Account::find($transaction->account_id);
            if ($transaction->type === 'income') {
                $account->balance -= $transaction->amount;
            } else {
                $account->balance += $transaction->amount;
            }
            $account->save();

            $transaction->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Транзакция успешно удалена'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при удалении транзакции'
            ], 500);
        }
    }

    /**
     * Массовое удаление транзакций
     */
    public function bulkDelete(Request $request)
    {
        try {
            $validated = $request->validate([
                'ids' => 'required|array',
                'ids.*' => 'exists:transactions,id'
            ]);

            $transactions = Transaction::where('user_id', $request->user()->id)
                ->whereIn('id', $validated['ids'])
                ->get();

            DB::beginTransaction();

            foreach ($transactions as $transaction) {
                $account = Account::find($transaction->account_id);
                if ($transaction->type === 'income') {
                    $account->balance -= $transaction->amount;
                } else {
                    $account->balance += $transaction->amount;
                }
                $account->save();

                $transaction->delete();
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => count($transactions) . ' транзакций успешно удалено'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при удалении транзакций'
            ], 500);
        }
    }
}
