<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Account extends Model
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
        'balance',
        'currency',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'balance' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Account types constants
     */
    const TYPE_CASH = 'cash';
    const TYPE_CARD = 'card';
    const TYPE_BANK = 'bank';
    const TYPE_ELECTRONIC = 'electronic';

    /**
     * Available account types
     */
    public static $types = [
        self::TYPE_CASH => 'Наличные',
        self::TYPE_CARD => 'Банковская карта',
        self::TYPE_BANK => 'Банковский счет',
        self::TYPE_ELECTRONIC => 'Электронный кошелек',
    ];

    /**
     * Currency constants
     */
    const CURRENCY_RUB = 'RUB';
    const CURRENCY_USD = 'USD';
    const CURRENCY_EUR = 'EUR';

    /**
     * Available currencies
     */
    public static $currencies = [
        self::CURRENCY_RUB => '₽',
        self::CURRENCY_USD => '$',
        self::CURRENCY_EUR => '€',
    ];

    /**
     * Get the user that owns the account
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get all transactions for the account
     */
    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * Get type label
     */
    public function getTypeLabelAttribute()
    {
        return self::$types[$this->type] ?? $this->type;
    }

    /**
     * Get currency symbol
     */
    public function getCurrencySymbolAttribute()
    {
        return self::$currencies[$this->currency] ?? $this->currency;
    }

    /**
     * Get formatted balance
     */
    public function getFormattedBalanceAttribute()
    {
        return number_format($this->balance, 2, '.', ' ') . ' ' . $this->currency_symbol;
    }

    /**
     * Get total income for this account
     */
    public function getTotalIncomeAttribute()
    {
        return $this->transactions()
            ->where('type', 'income')
            ->sum('amount');
    }

    /**
     * Get total expense for this account
     */
    public function getTotalExpenseAttribute()
    {
        return $this->transactions()
            ->where('type', 'expense')
            ->sum('amount');
    }

    /**
     * Scope a query to only include accounts of a given type
     */
    public function scopeOfType($query, $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope a query to only include accounts with positive balance
     */
    public function scopePositiveBalance($query)
    {
        return $query->where('balance', '>', 0);
    }
}
