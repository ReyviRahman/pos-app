<?php

namespace App\Http\Controllers;

use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\Transaction;
use App\Models\TransactionDetail;
use App\Services\MidtransService;
use Illuminate\Http\Request;

class MidtransWebhookController extends Controller
{
    public function handle(Request $request, MidtransService $midtransService)
    {
        $result = $midtransService->handleNotification();

        if (! $result || ! $result['status_changed']) {
            return response('OK', 200);
        }

        if ($result['transaction_status'] === 'settlement') {
            $transaction = Transaction::where('midtrans_order_id', $result['order_id'])->first();

            if ($transaction && $transaction->details->isEmpty()) {
                $cart = json_decode($transaction->xendit_metadata['cart'] ?? '[]', true);

                foreach ($cart as $item) {
                    TransactionDetail::create([
                        'transaction_id' => $transaction->id,
                        'product_id' => $item['id'],
                        'product_name' => $item['name'],
                        'quantity' => $item['qty'],
                        'price' => $item['price'],
                        'subtotal' => $item['price'] * $item['qty'],
                    ]);

                    $this->deductProductIngredients($item, $transaction->invoice_number);
                }
            }
        }

        return response('OK', 200);
    }

    protected function deductProductIngredients(array $item, string $invoiceNumber): void
    {
        $product = Product::with('ingredients')->find($item['id']);
        if ($product) {
            foreach ($product->ingredients as $ingredient) {
                $totalIngredientUsed = $item['qty'] * $ingredient->pivot->quantity_used;

                InventoryMovement::create([
                    'ingredient_id' => $ingredient->id,
                    'type' => 'out',
                    'quantity' => $totalIngredientUsed,
                    'price_per_unit' => $ingredient->price_per_unit,
                    'reference_id' => $invoiceNumber,
                ]);

                $ingredient->decrement('current_stock', $totalIngredientUsed);
            }
        }
    }
}
