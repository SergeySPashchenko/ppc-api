<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Gender extends Model
{
    /** @use HasFactory<\Database\Factories\GenderFactory> */
    use HasFactory;
    use HasSlug;
    use SoftDeletes;
    protected $fillable = ['gender_name', 'slug'];
    protected $primaryKey = 'gender_id';
    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('name')
            ->saveSlugsTo('slug')
            ->preventOverwrite();
    }
    public function getRouteKeyName(): string
    {
        return 'slug';
    }
    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'gender_id', 'gender_id');
    }
}
