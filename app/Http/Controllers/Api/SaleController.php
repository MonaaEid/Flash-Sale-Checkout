<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class SaleController extends Controller
{
    // Create a short-lived hold (reservation)
    public function createHold(Request $request)
    {
        $data = $request->validate([
            'quantity' => 'required|integer|min:1',
            'ttl_seconds' => 'nullable|integer|min:10|max:3600',
        ]);

        $productId = (int) $request->route('productId');
        $quantity = (int) $data['quantity'];
        $ttl = (int)($data['ttl_seconds'] ?? 120); // default 2 minutes

        $hold = DB::transaction(function () use ($productId, $quantity, $ttl) {
            $product = DB::table('products')->where('id', $productId)->lockForUpdate()->first();
            if (!$product) {
                return null;
            }

            $holds = DB::table('holds')
                ->where('product_id', $productId)
                ->where('status', 'active')
                ->where('expires_at', '>', now())
                ->sum('quantity');

            $sold = Order::sold_qty($id);

            $available = max(0, $product->stock - $holds - $sold);
            if ($available < $quantity) {
                return false; // not enough
            }

            $expiresAt = now()->addSeconds($ttl);

            $holdId = DB::table('holds')->insertGetId([
                'product_id' => $productId,
                'quantity' => $quantity,
                'expires_at' => $expiresAt,
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Invalidate cached available stock
            Cache::forget("product:{$productId}:available_stock");

            return DB::table('holds')->where('id', $holdId)->first();
        });

        if (is_null($hold)) {
            return response()->json(['message' => 'Product not found'], 404);
        }

        if ($hold === false) {
            return response()->json(['message' => 'Insufficient stock to create hold'], 409);
        }

        return response()->json([
            'hold_id' => $hold->id,
            'expires_at' => $hold->expires_at,
        ], 201);
    }

    // public function holdsIndex(Request $request)
    // {
    //     $holds = DB::table('holds')->get();
    //     if ($holds->isEmpty()) {
    //         return response()->json(['message' => 'No holds found'], 404);
    //     }

    //     return response()->json($holds);
    // }

    public function createOrder(Request $request)
    {
        $data = $request->validate([
            'hold_id' => 'required|integer',
            'idempotency_key' => 'required|string',
        ]);

        $holdId = (int)$data['hold_id'];
        $idempotencyKey = $data['idempotency_key'];

        $order = DB::transaction(function () use ($holdId, $idempotencyKey) {
            // If an order with this idempotency key exists, return it
            $existing = DB::table('orders')->where('idempotency_key', $idempotencyKey)->first();
            if ($existing) {
                return $existing;
            }

            // Lock the hold
            $hold = DB::table('holds')->where('id', $holdId)->lockForUpdate()->first();
            if (!$hold) {
                return null;
            }
            if ($hold->status !== 'active' || $hold->expires_at <= now()) {
                return false; // hold expired or not active
            }

            // Mark hold consumed to prevent reuse
            DB::table('holds')->where('id', $holdId)->update(['status' => 'consumed', 'updated_at' => now()]);

            $orderId = DB::table('orders')->insertGetId([
                'product_id' => $hold->product_id,
                'quantity' => $hold->quantity,
                'status' => 'pending',
                'hold_id' => $holdId,
                'idempotency_key' => $idempotencyKey,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Invalidate cache
            Cache::forget("product:{$hold->product_id}:available_stock");

            return DB::table('orders')->where('id', $orderId)->first();
        });

        if (is_null($order)) {
            return response()->json(['message' => 'Hold not found'], 404);
        }
        if ($order === false) {
            return response()->json(['message' => 'Hold expired or invalid'], 409);
        }

        return response()->json([
            'id' => $order->id,
            'product_id' => $order->product_id,
            'quantity' => (int)$order->quantity,
            'status' => $order->status,
            'idempotency_key' => $order->idempotency_key,
        ], 201);
    }

    // Idempotent payment webhook
   public function paymentWebhook(Request $request)
    {
        $data = $request->validate([
            'order_id' => 'required|integer',
            'status' => 'required|string|in:success,failure',
            'idempotency_key' => 'required|string',
        ]);

        $orderId = (int)$data['order_id'];
        $status = $data['status'];
        $idempotencyKey = $data['idempotency_key'];

        $result = DB::transaction(function () use ($orderId, $status, $idempotencyKey) {
            // Check if this webhook has already been processed
            $existing = DB::table('payment_webhooks')
                ->where('idempotency_key', $idempotencyKey)
                ->first();

            if ($existing) {
                return 'already_processed';
            }

            // Lock the order
            $order = DB::table('orders')->where('id', $orderId)->lockForUpdate()->first();
            if (!$order) {
                return null;
            }

            if ($order->status !== 'pending') {
                // Record webhook anyway for dedupe
                DB::table('payment_webhooks')->insert([
                    'order_id' => $orderId,
                    'status' => $status,
                    'idempotency_key' => $idempotencyKey,
                    'created_at' => now(),
                ]);
                return 'already_processed';
            }

            if ($status === 'success') {
                DB::table('orders')->where('id', $orderId)
                    ->update(['status' => 'completed', 'updated_at' => now()]);
            } else {
                DB::table('orders')->where('id', $orderId)
                    ->update(['status' => 'canceled', 'updated_at' => now()]);
                DB::table('holds')->where('id', $order->hold_id)
                    ->update(['status' => 'released', 'updated_at' => now()]);
            }

            // Record the webhook processing
            DB::table('payment_webhooks')->insert([
                'order_id' => $orderId,
                'status' => $status,
                'idempotency_key' => $idempotencyKey,
                'created_at' => now(),
            ]);

            Cache::forget("product:{$order->product_id}:available_stock");

            return 'processed';
        });

        if (is_null($result)) {
            return response()->json(['message' => 'Order not found'], 404);
        }
        if ($result === 'already_processed') {
            return response()->json(['message' => 'Webhook already processed'], 200);
        }
        return response()->json(['message' => 'Payment webhook processed'], 200);
    }
}
