<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'category_id',
        'account_id',
        'type',
        'amount',
        'date',
        'description',
        'note',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'amount' => 'decimal:2',
        'date' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Transaction type constants
     */
    const TYPE_INCOME = 'income';
    const TYPE_EXPENSE = 'expense';

    /**
     * Available transaction types
     */
    public static $types = [
        self::TYPE_INCOME => 'Доход',
        self::TYPE_EXPENSE => 'Расход',
    ];

    /**
     * Get the user that owns the transaction
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the category of the transaction
     */
    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Get the account of the transaction
     */
    public function account()
    {
        return $this->belongsTo(Account::class);
    }

    /**
     * Get type label
     */
    public function getTypeLabelAttribute()
    {
        return self::$types[$this->type] ?? $this->type;
    }

    /**
     * Get formatted amount with sign
     */
    public function getFormattedAmountAttribute()
    {
        $sign = $this->type === self::TYPE_INCOME ? '+' : '-';
        $amount = number_format($this->amount, 2, '.', ' ');
        return "{$sign}{$amount} {$this->account->currency_symbol}";
    }

    /**
     * Get absolute amount (always positive)
     */
    public function getAbsoluteAmountAttribute()
    {
        return abs($this->amount);
    }

    /**
     * Get short description (truncated)
     */
    public function getShortDescriptionAttribute()
    {
        if (!$this->description) {
            return '-';
        }
        return strlen($this->description) > 50
            ? substr($this->description, 0, 47) . '...'
            : $this->description;
    }

    /**
     * Check if transaction is income
     */
    public function isIncome()
    {
        return $this->type === self::TYPE_INCOME;
    }

    /**
     * Check if transaction is expense
     */
    public function isExpense()
    {
        return $this->type === self::TYPE_EXPENSE;
    }

    /**
     * Scope a query to only include income transactions
     */
    public function scopeIncome($query)
    {
        return $query->where('type', self::TYPE_INCOME);
    }

    /**
     * Scope a query to only include expense transactions
     */
    public function scopeExpense($query)
    {
        return $query->where('type', self::TYPE_EXPENSE);
    }

    /**
     * Scope a query to only include transactions for current month
     */
    public function scopeCurrentMonth($query)
    {
        return $query->whereMonth('date', now()->month)
            ->whereYear('date', now()->year);
    }

    /**
     * Scope a query to only include transactions for current week
     */
    public function scopeCurrentWeek($query)
    {
        return $query->whereBetween('date', [now()->startOfWeek(), now()->endOfWeek()]);
    }

    /**
     * Scope a query to only include transactions for today
     */
    public function scopeToday($query)
    {
        return $query->whereDate('date', now()->toDateString());
    }

    /**
     * Scope a query to filter by date range
     */
    public function scopeDateBetween($query, $startDate, $endDate)
    {
        return $query->whereBetween('date', [$startDate, $endDate]);
    }

    /**
     * Scope a query to filter by amount range
     */
    public function scopeAmountBetween($query, $min, $max)
    {
        return $query->whereBetween('amount', [$min, $max]);
    }
}
