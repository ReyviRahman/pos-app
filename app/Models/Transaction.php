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
        'xendit_payment_request_id',
        'xendit_payment_url',
        'xendit_payment_status',
        'xendit_channel_code',
        'xendit_metadata',
        'midtrans_order_id',
        'midtrans_qr_string',
        'midtrans_redirect_url',
        'midtrans_snap_token',
    ];

    protected $casts = [
        'xendit_metadata' => 'array',
        'total_amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'change_amount' => 'decimal:2',
    ];

    public function details(): HasMany
    {
        return $this->hasMany(TransactionDetail::class);
    }

    public function isPending(): bool
    {
        return $this->xendit_payment_status === 'PENDING' && $this->status !== 'completed';
    }

    public function isPaid(): bool
    {
        return $this->status === 'completed' && $this->xendit_payment_status === 'SUCCEEDED';
    }

    public function isFailed(): bool
    {
        return in_array($this->xendit_payment_status, ['FAILED', 'EXPIRED']);
    }

    public function getPaymentStatusLabel(): string
    {
        return match ($this->xendit_payment_status) {
            'SUCCEEDED' => 'Lunas',
            'FAILED' => 'Gagal',
            'EXPIRED' => 'Kadaluarsa',
            'REQUIRES_ACTION' => 'Menunggu Aksi',
            default => 'Menunggu Pembayaran',
        };
    }

    public function getPaymentStatusColor(): string
    {
        return match ($this->xendit_payment_status) {
            'SUCCEEDED' => 'green',
            'FAILED' => 'red',
            'EXPIRED' => 'gray',
            'REQUIRES_ACTION' => 'yellow',
            default => 'blue',
        };
    }
}
