<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'name',
        'type',
        'icon',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Category type constants
     */
    const TYPE_INCOME = 'income';
    const TYPE_EXPENSE = 'expense';

    /**
     * Available category types
     */
    public static $types = [
        self::TYPE_INCOME => 'Доход',
        self::TYPE_EXPENSE => 'Расход',
    ];

    /**
     * Get the user that owns the category
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get all transactions for the category
     */
    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * Get all budgets for the category
     */
    public function budgets()
    {
        return $this->hasMany(Budget::class);
    }

    /**
     * Get type label
     */
    public function getTypeLabelAttribute()
    {
        return self::$types[$this->type] ?? $this->type;
    }

    /**
     * Get total amount spent/earned in this category
     */
    public function getTotalAmountAttribute()
    {
        return $this->transactions()->sum('amount');
    }

    /**
     * Get total amount for current month
     */
    public function getCurrentMonthAmountAttribute()
    {
        return $this->transactions()
            ->whereMonth('date', now()->month)
            ->whereYear('date', now()->year)
            ->sum('amount');
    }

    /**
     * Get transaction count
     */
    public function getTransactionCountAttribute()
    {
        return $this->transactions()->count();
    }

    /**
     * Get average transaction amount
     */
    public function getAverageAmountAttribute()
    {
        $count = $this->transaction_count;
        return $count > 0 ? $this->total_amount / $count : 0;
    }

    /**
     * Scope a query to only include income categories
     */
    public function scopeIncome($query)
    {
        return $query->where('type', self::TYPE_INCOME);
    }

    /**
     * Scope a query to only include expense categories
     */
    public function scopeExpense($query)
    {
        return $query->where('type', self::TYPE_EXPENSE);
    }

    /**
     * Check if category has transactions
     */
    public function hasTransactions()
    {
        return $this->transactions()->exists();
    }
}
