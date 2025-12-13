<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ExpensetypeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

final class Expensetype extends Model
{
    /** @use HasFactory<ExpensetypeFactory> */
    use HasFactory;

    use HasSlug;
    use SoftDeletes;

    protected $primaryKey = 'ExpenseID';

    protected $fillable = [
        'Name',
        'slug',
    ];

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('Name')
            ->saveSlugsTo('slug')
            ->slugsShouldBeNoLongerThan(50)
            ->doNotGenerateSlugsOnUpdate();
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * @return HasMany<Expense, $this>
     */
    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class, 'ExpenseID', 'ExpenseID');
    }
}
