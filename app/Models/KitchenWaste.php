<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KitchenWaste extends Model
{
    protected $fillable = [
        'branch_id',
        'order_item_id',
        'product_id',
        'quantity',
        'reason',
        'chef_name',
    ];

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function orderItem()
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
