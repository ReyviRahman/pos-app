<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Log;
use Xendit\Configuration;
use Xendit\PaymentRequest\PaymentRequestApi;
use Xendit\PaymentRequest\PaymentRequestParameters;
use Xendit\XenditSdkException;

class XenditService
{
    protected PaymentRequestApi $paymentRequestApi;

    public function __construct()
    {
        Configuration::setXenditKey(config('services.xendit.secret_key'));
        $this->paymentRequestApi = new PaymentRequestApi;
    }

    public function createPayment(array $data): array
    {
        $params = new PaymentRequestParameters([
            'reference_id' => $data['reference_id'],
            'type' => 'PAY',
            'country' => 'ID',
            'currency' => 'IDR',
            'request_amount' => $data['amount'],
            'capture_method' => 'AUTOMATIC',
            'channel_code' => $data['channel_code'],
            'channel_properties' => $data['channel_properties'],
            'description' => $data['description'] ?? 'Payment for order '.$data['reference_id'],
            'metadata' => $data['metadata'] ?? [],
        ]);

        try {
            Log::info('Xendit Payment Request', [
                'reference_id' => $data['reference_id'],
                'amount' => $data['amount'],
                'channel_code' => $data['channel_code'],
                'channel_properties' => $data['channel_properties'],
            ]);

            $response = $this->paymentRequestApi->createPaymentRequest($params);

            $paymentMethod = $response->getPaymentMethod();
            $channelCode = $paymentMethod ? ($paymentMethod['channel_code'] ?? $paymentMethod->getChannelCode() ?? null) : null;

            Log::info('Xendit Payment Response', [
                'payment_request_id' => $response->getId(),
                'status' => $response->getStatus(),
                'channel_code' => $channelCode,
            ]);

            return [
                'success' => true,
                'payment_request_id' => $response->getId(),
                'status' => $response->getStatus(),
                'actions' => $response->getActions(),
                'channel_code' => $channelCode,
                'payment_url' => $this->extractPaymentUrl($response->getActions()),
            ];
        } catch (XenditSdkException $e) {
            Log::error('Xendit XenditSdkException', [
                'status' => $e->getStatus(),
                'error_code' => $e->getErrorCode(),
                'error_message' => $e->getErrorMessage(),
                'full_error' => $e->getFullError(),
                'raw_response' => $e->getRawResponse(),
            ]);

            return [
                'success' => false,
                'message' => $e->getErrorMessage() ?? $e->getMessage(),
                'error_code' => $e->getErrorCode(),
                'status' => $e->getStatus(),
            ];
        } catch (Exception $e) {
            Log::error('Xendit General Exception', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    public function handleWebhook(array $payload): array
    {
        $callbackToken = config('services.xendit.callback_token');

        if ($callbackToken && isset($payload['callback_token'])) {
            if ($payload['callback_token'] !== $callbackToken) {
                return [
                    'success' => false,
                    'message' => 'Invalid callback token',
                ];
            }
        }

        return [
            'success' => true,
            'payment_request_id' => $payload['payment_request_id'] ?? null,
            'status' => $payload['status'] ?? null,
            'channel_code' => $payload['channel_code'] ?? null,
            'amount' => $payload['amount'] ?? null,
            'metadata' => $payload['metadata'] ?? [],
        ];
    }

    protected function extractPaymentUrl(?array $actions): ?string
    {
        if (! $actions) {
            return null;
        }

        foreach ($actions as $action) {
            if (isset($action['type']) && $action['type'] === 'REDIRECT_CUSTOMER') {
                return $action['value'] ?? null;
            }
        }

        return null;
    }
}
