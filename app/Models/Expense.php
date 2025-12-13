<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\AccessibleByUserUniversalTrait;
use Database\Factories\ExpenseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

final class Expense extends Model
{
    use AccessibleByUserUniversalTrait;

    protected static function boot(): void
    {
        parent::boot();
        self::$cacheAccess = true;
    }

    /** @use HasFactory<ExpenseFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $fillable = [
        'ProductID',
        'ExpenseID',
        'ExpenseDate',
        'Expense',
    ];

    /**
     * Назва батьківського відношення для рекурсії доступу
     */
    protected function parentRelation(): ?string
    {
        return 'product';
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'ProductID', 'ProductID');
    }

    /**
     * @return BelongsTo<Expensetype, $this>
     */
    public function expensetype(): BelongsTo
    {
        return $this->belongsTo(Expensetype::class, 'ExpenseID', 'ExpenseID');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'ExpenseDate' => 'date',
            'Expense' => 'decimal:2',
        ];
    }
}
