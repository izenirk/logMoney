<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Регистрация нового пользователя
     */
    public function register(Request $request)
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'email' => 'required|email|unique:users,email',
                'password' => 'required|min:6|confirmed',
            ]);

            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
            ]);

            // создаем стандартные категории для нового пользователя
            $this->createDefaultCategories($user);

            // создаем стандартные счета
            $this->createDefaultAccounts($user);

            Auth::login($user);

            return response()->json([
                'success' => true,
                'user' => $user,
                'message' => 'Регистрация успешно завершена'
            ], 201);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'errors' => $e->errors(),
                'message' => 'Ошибка валидации'
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Произошла ошибка при регистрации'
            ], 500);
        }
    }

    /**
     * Вход пользователя
     */
    public function login(Request $request)
    {
        try {
            $credentials = $request->validate([
                'email' => 'required|email',
                'password' => 'required',
            ]);

            if (Auth::attempt($credentials, true)) {
                $request->session()->regenerate();

                return response()->json([
                    'success' => true,
                    'user' => Auth::user(),
                    'message' => 'Вход выполнен успешно'
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Неверный email или пароль'
            ], 401);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Произошла ошибка при входе'
            ], 500);
        }
    }

    /**
     * Выход пользователя
     */
    public function logout(Request $request)
    {
        try {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return response()->json([
                'success' => true,
                'message' => 'Выход выполнен успешно'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Произошла ошибка при выходе'
            ], 500);
        }
    }

    /**
     * Получение текущего пользователя
     */
    public function user(Request $request)
    {
        try {
            $user = $request->user();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Пользователь не авторизован'
                ], 401);
            }

            return response()->json([
                'success' => true,
                'user' => $user
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Произошла ошибка'
            ], 500);
        }
    }

    /**
     * Создание стандартных категорий для нового пользователя
     */
    private function createDefaultCategories($user)
    {
        $categories = [ //ToDo посмотреть актуальные списки разходов и доходов (обновить списки при создании пользователей)
            // Категории расходов
            ['name' => 'Продукты', 'type' => 'expense', 'icon' => '🛒'],
            ['name' => 'Транспорт', 'type' => 'expense', 'icon' => '🚗'],
            ['name' => 'Кафе и рестораны', 'type' => 'expense', 'icon' => '🍽️'],
            ['name' => 'Развлечения', 'type' => 'expense', 'icon' => '🎬'],
            ['name' => 'Здоровье', 'type' => 'expense', 'icon' => '🏥'],
            ['name' => 'Одежда', 'type' => 'expense', 'icon' => '👕'],
            ['name' => 'Коммунальные услуги', 'type' => 'expense', 'icon' => '💡'],
            ['name' => 'Связь и интернет', 'type' => 'expense', 'icon' => '📱'],
            ['name' => 'Образование', 'type' => 'expense', 'icon' => '📚'],

            // Категории доходов
            ['name' => 'Зарплата', 'type' => 'income', 'icon' => '💰'],
            ['name' => 'Фриланс', 'type' => 'income', 'icon' => '💻'],
            ['name' => 'Подарки', 'type' => 'income', 'icon' => '🎁'],
            ['name' => 'Инвестиции', 'type' => 'income', 'icon' => '📈'],
        ];

        foreach ($categories as $category) {
            $user->categories()->create($category);
        }
    }

    /**
     * Создание стандартных счетов для нового пользователя
     */
    private function createDefaultAccounts($user)
    {
        $accounts = [
            ['name' => 'Наличные', 'type' => 'cash', 'balance' => 0, 'currency' => 'RUB'],
            ['name' => 'Дебетовая карта', 'type' => 'card', 'balance' => 0, 'currency' => 'RUB'],
            ['name' => 'Копилка', 'type' => 'bank', 'balance' => 0, 'currency' => 'RUB'],
        ];

        foreach ($accounts as $account) {
            $user->accounts()->create($account);
        }
    }
}
