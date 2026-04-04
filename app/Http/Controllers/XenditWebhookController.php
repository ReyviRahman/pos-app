<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Services\XenditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class XenditWebhookController extends Controller
{
    protected XenditService $xenditService;

    public function __construct(XenditService $xenditService)
    {
        $this->xenditService = $xenditService;
    }

    public function handle(Request $request)
    {
        $payload = $request->all();

        Log::info('Xendit webhook received', ['payload' => $payload]);

        $result = $this->xenditService->handleWebhook($payload);

        if (! $result['success']) {
            Log::error('Xendit webhook validation failed', ['message' => $result['message']]);

            return response()->json(['error' => 'Invalid webhook'], 401);
        }

        if (! isset($payload['payment_request_id'])) {
            return response()->json(['error' => 'Missing payment_request_id'], 400);
        }

        $transaction = Transaction::where('xendit_payment_request_id', $payload['payment_request_id'])->first();

        if (! $transaction) {
            Log::warning('Transaction not found for Xendit webhook', [
                'payment_request_id' => $payload['payment_request_id'],
            ]);

            return response()->json(['error' => 'Transaction not found'], 404);
        }

        $status = $payload['status'] ?? null;

        if ($status === 'SUCCEEDED') {
            DB::transaction(function () use ($transaction, $payload) {
                $transaction->update([
                    'xendit_payment_status' => 'SUCCEEDED',
                    'status' => 'completed',
                    'xendit_metadata' => $payload,
                ]);

                foreach ($transaction->details as $detail) {
                    if ($detail->product_id) {
                        $product = $detail->product;
                        if ($product && $product->ingredients) {
                            foreach ($product->ingredients as $ingredient) {
                                $totalIngredientUsed = $detail->quantity * $ingredient->pivot->quantity_used;

                                DB::table('inventory_movements')->insert([
                                    'ingredient_id' => $ingredient->id,
                                    'type' => 'out',
                                    'quantity' => $totalIngredientUsed,
                                    'price_per_unit' => $ingredient->price_per_unit,
                                    'reference_id' => $transaction->invoice_number,
                                    'created_at' => now(),
                                    'updated_at' => now(),
                                ]);

                                $ingredient->decrement('current_stock', $totalIngredientUsed);
                            }
                        }
                    }
                }
            });

            Log::info('Transaction completed via Xendit webhook', [
                'invoice_number' => $transaction->invoice_number,
            ]);
        } elseif (in_array($status, ['FAILED', 'EXPIRED'])) {
            $transaction->update([
                'xendit_payment_status' => $status,
                'status' => 'canceled',
                'xendit_metadata' => $payload,
            ]);

            Log::info('Transaction '.strtolower($status).' via Xendit webhook', [
                'invoice_number' => $transaction->invoice_number,
            ]);
        }

        return response()->json(['success' => true]);
    }
}
