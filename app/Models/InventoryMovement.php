<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventoryMovement extends Model
{
    protected $fillable = [
        'branch_id',
        'ingredient_id',
        'type',
        'quantity',
        'price_per_unit',
        'reference_id',
    ];

    public function ingredient()
    {
        return $this->belongsTo(Ingredient::class);
    }
}
