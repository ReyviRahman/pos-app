<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Transaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'branch_id',
        'username_cashier',
        'customer_name',
        'table_number',
        'invoice_number',
        'total_amount',
        'paid_amount',
        'change_amount',
        'payment_method',
        'status',
        'karyawan_id',
        'potongan_makan',
        'tanggungan_karyawan',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'change_amount' => 'decimal:2',
        'potongan_makan' => 'decimal:2',
        'tanggungan_karyawan' => 'decimal:2',
    ];

    public function details(): HasMany
    {
        return $this->hasMany(TransactionDetail::class);
    }

    public function karyawan()
    {
        return $this->belongsTo(Karyawan::class);
    }
}
