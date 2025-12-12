<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

class Gender extends Model
{
    /** @use HasFactory<\Database\Factories\GenderFactory> */
    use HasFactory;
    use HasSlug;
    use SoftDeletes;

    protected $fillable = [
        'gender_name',
        'gender_slug',
    ];

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('gender_name')
            ->saveSlugsTo('gender_slug');
    }

    public function getRouteKeyName(): string
    {
        return 'gender_slug';
    }
}
