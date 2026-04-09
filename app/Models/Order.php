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
    ];

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }
}
