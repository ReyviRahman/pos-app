<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class MenuIngredient extends Pivot
{
    // Beritahu Laravel nama tabel spesifiknya
    protected $table = 'menu_ingredients';

    // Kolom yang bisa diisi secara massal
    protected $fillable = [
        'product_id',
        'ingredient_id',
        'quantity_used',
    ];
}
