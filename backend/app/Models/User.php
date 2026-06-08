<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get all transactions for the user
     */
    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * Get all accounts for the user
     */
    public function accounts()
    {
        return $this->hasMany(Account::class);
    }

    /**
     * Get all categories for the user
     */
    public function categories()
    {
        return $this->hasMany(Category::class);
    }

    /**
     * Get all budgets for the user
     */
    public function budgets()
    {
        return $this->hasMany(Budget::class);
    }

    /**
     * Get total balance of all accounts
     */
    public function getTotalBalanceAttribute()
    {
        return $this->accounts()->sum('balance');
    }

    /**
     * Get total income for current month
     */
    public function getCurrentMonthIncomeAttribute()
    {
        return $this->transactions()
            ->where('type', 'income')
            ->whereMonth('date', now()->month)
            ->whereYear('date', now()->year)
            ->sum('amount');
    }

    /**
     * Get total expense for current month
     */
    public function getCurrentMonthExpenseAttribute()
    {
        return $this->transactions()
            ->where('type', 'expense')
            ->whereMonth('date', now()->month)
            ->whereYear('date', now()->year)
            ->sum('amount');
    }

    /**
     * Get current month balance
     */
    public function getCurrentMonthBalanceAttribute()
    {
        return $this->current_month_income - $this->current_month_expense;
    }
}
