<?php

namespace App\Services;

use App\Models\Transaction;
use Midtrans\Config;
use Midtrans\CoreApi;
use Midtrans\Notification;

class MidtransService
{
    public function __construct()
    {
        Config::$serverKey = config('services.midtrans.server_key');
        Config::$isProduction = (bool) config('services.midtrans.is_production');
        Config::$isSanitized = true;
        Config::$overrideNotifUrl = config('services.midtrans.notif_url');
    }

    public function createQrisPayment(float $amount, string $orderId): array
    {
        $params = [
            'payment_type' => 'qris',
            'transaction_details' => [
                'order_id' => $orderId,
                'gross_amount' => (int) $amount,
            ],
            'custom_expiry' => [
                'order_time' => now()->format('Y-m-d H:i:s O'),
                'expiry_duration' => 5,
                'unit' => 'minutes',
            ],
        ];

        try {
            $response = CoreApi::charge($params);

            return [
                'success' => true,
                'qr_string' => $response->qr_string ?? null,
                'order_id' => $response->order_id ?? null,
                'status' => $response->transaction_status ?? 'pending',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    public function createGopayPayment(float $amount, string $orderId): array
    {
        $params = [
            'payment_type' => 'gopay',
            'transaction_details' => [
                'order_id' => $orderId,
                'gross_amount' => (int) $amount,
            ],
            'gopay' => [
                'enable_callback' => true,
            ],
        ];

        try {
            $response = CoreApi::charge($params);

            $qrUrl = null;
            $deeplinkUrl = null;

            if (isset($response->actions)) {
                foreach ($response->actions as $action) {
                    if ($action->name === 'generate-qr-code') {
                        $qrUrl = $action->url;
                    }
                    if ($action->name === 'deeplink-redirect') {
                        $deeplinkUrl = $action->url;
                    }
                }
            }

            return [
                'success' => true,
                'qr_url' => $qrUrl,
                'deeplink_url' => $deeplinkUrl,
                'order_id' => $response->order_id ?? null,
                'status' => $response->transaction_status ?? 'pending',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    public function createBriPayment(float $amount, string $orderId): array
    {
        $params = [
            'payment_type' => 'bri_epay',
            'transaction_details' => [
                'order_id' => $orderId,
                'gross_amount' => (int) $amount,
            ],
        ];

        try {
            $response = CoreApi::charge($params);

            $redirectUrl = null;

            if (isset($response->redirect_url)) {
                $redirectUrl = $response->redirect_url;
            }

            return [
                'success' => true,
                'redirect_url' => $redirectUrl,
                'va_number' => $response->va_numbers[0]->va_number ?? null,
                'bank' => $response->va_numbers[0]->bank ?? null,
                'order_id' => $response->order_id ?? null,
                'status' => $response->transaction_status ?? 'pending',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    public function handleNotification(): ?array
    {
        try {
            $notification = new Notification;

            $orderId = $notification->order_id;
            $transactionStatus = $notification->transaction_status;

            $transaction = Transaction::where('midtrans_order_id', $orderId)->first();

            if (! $transaction) {
                return null;
            }

            $statusChanged = false;

            if ($transactionStatus === 'settlement') {
                if ($transaction->status !== 'completed') {
                    $transaction->update([
                        'status' => 'completed',
                        'xendit_payment_status' => 'SUCCEEDED',
                    ]);
                    $statusChanged = true;
                }
            } elseif (in_array($transactionStatus, ['expire', 'cancel'])) {
                if ($transaction->status !== 'canceled') {
                    $transaction->update([
                        'status' => 'canceled',
                        'xendit_payment_status' => 'EXPIRED',
                    ]);
                    $statusChanged = true;
                }
            } elseif ($transactionStatus === 'deny') {
                if ($transaction->status !== 'canceled') {
                    $transaction->update([
                        'status' => 'canceled',
                        'xendit_payment_status' => 'FAILED',
                    ]);
                    $statusChanged = true;
                }
            }

            return [
                'order_id' => $orderId,
                'transaction_status' => $transactionStatus,
                'status_changed' => $statusChanged,
            ];
        } catch (\Exception $e) {
            return null;
        }
    }
}
