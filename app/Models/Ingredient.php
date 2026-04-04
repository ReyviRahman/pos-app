<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Ingredient extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'current_stock',
        'unit',
        'price_per_unit',
    ];

    /**
     * Relasi balik ke model Product.
     */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'menu_ingredients')
            ->withPivot('id', 'quantity_used')
            ->withTimestamps();
    }
}
