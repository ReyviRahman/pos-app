<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Product extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'branch_id',
        'name',
        'price',
    ];

    /**
     * Relasi ke model Ingredient.
     */
    public function ingredients(): BelongsToMany
    {
        // Parameter: Model Tujuan, Nama Tabel Pivot
        return $this->belongsToMany(Ingredient::class, 'menu_ingredients')
            ->withPivot('id', 'quantity_used') // Mengambil kolom tambahan di tabel pivot
            ->withTimestamps(); // Memastikan timestamp tabel pivot ikut terupdate/tercatat
    }
}
