<?php

namespace App\Models;

use Database\Factories\CategoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    /** @use HasFactory<CategoryFactory> */
    use HasFactory;
    protected $fillable = [
        'name',
        'slug',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class);
    }

    public function isInUse(): bool
    {
        return $this->activities()->exists();
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
