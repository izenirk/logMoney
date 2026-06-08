<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Budget extends Model
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
        'limit_amount',
        'month',
        'year',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'limit_amount' => 'decimal:2',
        'month' => 'integer',
        'year' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Status constants
     */
    const STATUS_OK = 'ok';
    const STATUS_WARNING = 'warning';
    const STATUS_EXCEEDED = 'exceeded';

    /**
     * Status labels
     */
    public static $statusLabels = [
        self::STATUS_OK => 'В пределах нормы',
        self::STATUS_WARNING => '接近 лимита',
        self::STATUS_EXCEEDED => 'Превышен',
    ];

    /**
     * Get the user that owns the budget
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the category of the budget
     */
    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Get spent amount for this budget
     */
    public function getSpentAttribute()
    {
        return Transaction::where('user_id', $this->user_id)
            ->where('type', 'expense')
            ->where('category_id', $this->category_id)
            ->whereMonth('date', $this->month)
            ->whereYear('date', $this->year)
            ->sum('amount');
    }

    /**
     * Get remaining amount
     */
    public function getRemainingAttribute()
    {
        return $this->limit_amount - $this->spent;
    }

    /**
     * Get percentage of budget used
     */
    public function getPercentageAttribute()
    {
        if ($this->limit_amount <= 0) {
            return 0;
        }
        $percentage = ($this->spent / $this->limit_amount) * 100;
        return min(100, round($percentage, 2));
    }

    /**
     * Get status of budget based on spent amount
     */
    public function getStatusAttribute()
    {
        if ($this->spent >= $this->limit_amount) {
            return self::STATUS_EXCEEDED;
        } elseif ($this->spent >= $this->limit_amount * 0.9) {
            return self::STATUS_WARNING;
        }
        return self::STATUS_OK;
    }

    /**
     * Get status label
     */
    public function getStatusLabelAttribute()
    {
        return self::$statusLabels[$this->status] ?? 'Неизвестно';
    }

    /**
     * Get formatted limit amount
     */
    public function getFormattedLimitAttribute()
    {
        return number_format($this->limit_amount, 2, '.', ' ') . ' ₽';
    }

    /**
     * Get formatted spent amount
     */
    public function getFormattedSpentAttribute()
    {
        return number_format($this->spent, 2, '.', ' ') . ' ₽';
    }

    /**
     * Get formatted remaining amount
     */
    public function getFormattedRemainingAttribute()
    {
        $remaining = $this->remaining;
        $formatted = number_format(abs($remaining), 2, '.', ' ');
        return ($remaining < 0 ? '-' : '') . $formatted . ' ₽';
    }

    /**
     * Check if budget is active (for current or future month)
     */
    public function isActive()
    {
        $budgetDate = \Carbon\Carbon::create($this->year, $this->month, 1);
        return $budgetDate->gte(now()->startOfMonth());
    }

    /**
     * Check if budget is exceeded
     */
    public function isExceeded()
    {
        return $this->status === self::STATUS_EXCEEDED;
    }

    /**
     * Check if budget is close to limit (90% or more)
     */
    public function isCloseToLimit()
    {
        return $this->percentage >= 90 && $this->percentage < 100;
    }

    /**
     * Scope a query to only include budgets for current month
     */
    public function scopeCurrentMonth($query)
    {
        return $query->where('month', now()->month)
            ->where('year', now()->year);
    }

    /**
     * Scope a query to only include budgets for specific month/year
     */
    public function scopeForMonth($query, $month, $year)
    {
        return $query->where('month', $month)->where('year', $year);
    }

    /**
     * Scope a query to only include budgets that are not exceeded
     */
    public function scopeNotExceeded($query)
    {
        return $query->whereHas('category', function ($q) {
            $q->where('type', 'expense');
        })->get()->filter(function ($budget) {
            return !$budget->isExceeded();
        });
    }

    /**
     * Get daily average spending for this category in current month
     */
    public function getDailyAverageAttribute()
    {
        $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $this->month, $this->year);
        return $daysInMonth > 0 ? $this->spent / $daysInMonth : 0;
    }

    /**
     * Get projected spending by end of month
     */
    public function getProjectedSpendingAttribute()
    {
        $daysPassed = now()->day;
        $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $this->month, $this->year);

        if ($daysPassed === 0) {
            return $this->spent;
        }

        return ($this->spent / $daysPassed) * $daysInMonth;
    }
}
