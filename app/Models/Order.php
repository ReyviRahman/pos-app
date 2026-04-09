<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $fillable = [
        'branch_id',
        'order_number',
        'username_cashier',
        'customer_name',
        'table_number',
        'total_price',
        'status',
        'kitchen_status',
        'karyawan_id',
        'potongan_makan',
        'metode_pembayaran_karyawan',
    ];

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function karyawan()
    {
        return $this->belongsTo(Karyawan::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }
}
