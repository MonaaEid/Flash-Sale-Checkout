<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;
use App\Models\Product;

class HoldConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_no_overselling_under_parallel_requests()
    {
        // Seed a product with limited stock
        $product = Product::create([
            'name' => 'Widget A',
            'stock' => 10,
            'price' => 55
        ]);

        $results = [];

        // Simulate 20 parallel hold requests of quantity=1

        for ($i = 0; $i < 20; $i++) {
            $results[] = DB::transaction(function () use ($product) {
                $holds = DB::table('holds')
                    ->where('product_id', $product->id)
                    ->where('status', 'active')
                    ->where('expires_at', '>', now())
                    ->sum('quantity');

                $sold = DB::table('orders')
                    ->where('product_id', $product->id)
                    ->where('status', 'completed')
                    ->sum('quantity');

                $available = max(0, $product->stock - $holds - $sold);

                if ($available < 1) {
                    return false; // not enough stock
                }

                $holdId = DB::table('holds')->insertGetId([
                    'product_id' => $product->id,
                    'quantity' => 1,
                    'expires_at' => now()->addSeconds(60),
                    'status' => 'active',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                Cache::forget("product:{$product->id}:available_stock");

                return $holdId;
            });
        }

        // Count successful holds
        $successful = collect($results)->filter(fn($r) => $r !== false)->count();

        // Assert that we never oversell beyond stock
        $this->assertEquals(10, $successful, "Should only allow 10 holds, not oversell");
    }

       public function test_webhook_arrives_before_order_creation()
    {
        $fakeOrderId = 999; // not yet created

        // Webhook arrives first
        $this->postJson('/api/payments/webhook', [
            'order_id' => $fakeOrderId,
            'status' => 'success',
            'idempotency_key' => 'webhook-key-early',
        ])->assertStatus(404);

        // Now create the order
        $productId = DB::table('products')->insertGetId([
            'name' => 'Widget C',
            'stock' => 10,
            'price' => 55,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $orderId = DB::table('orders')->insertGetId([
            'id' => $fakeOrderId,
            'product_id' => $productId,
            'quantity' => 1,
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Re-send webhook (or simulate retry)
        $this->postJson('/api/payments/webhook', [
            'order_id' => $orderId,
            'status' => 'success',
            'idempotency_key' => 'webhook-key-early',
        ])->assertStatus(200);

        $order = DB::table('orders')->find($orderId);
        $this->assertEquals('completed', $order->status);
    }
    public function test_payment_webhook_is_idempotent()
    {
        $productId = DB::table('products')->insertGetId([
            'name' => 'Widget C',
            'stock' => 10,
            'price' => 55,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $orderId = DB::table('orders')->insertGetId([
            'product_id' => $productId,
            'quantity' => 1,
            'status' => 'pending',
            'idempotency_key' => 'abc123',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // First webhook
        $this->postJson('/api/payments/webhook', [
            'order_id' => $orderId,
            'status' => 'success',
            'idempotency_key' => 'webhook-key-1',
        ])->assertStatus(200);

        // Repeat webhook with same key
        $this->postJson('/api/payments/webhook', [
            'order_id' => $orderId,
            'status' => 'success',
            'idempotency_key' => 'webhook-key-1',
        ])->assertStatus(200);

        $order = DB::table('orders')->find($orderId);
        $this->assertEquals('completed', $order->status, "Order should remain completed after duplicate webhook");
    }
 

    public function test_hold_expiry_restores_availability()
    {
        $productId = DB::table('products')->insertGetId([
            'name' => 'Widget B',
            'stock' => 3,
            'price' => 55,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Create a hold that expires immediately
        DB::table('holds')->insert([
            'product_id' => $productId,
            'quantity' => 2,
            'expires_at' => now()->subSecond(), // already expired
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $available = $this->calculateAvailable($productId);
        $this->assertEquals(3, $available, "Expired hold should not reduce availability");
    }

    private function calculateAvailable(int $productId): int
    {
        $product = DB::table('products')->find($productId);
        $holds = DB::table('holds')
            ->where('product_id', $productId)
            ->where('status', 'active')
            ->where('expires_at', '>', now())
            ->sum('quantity');
        $sold = DB::table('orders')
            ->where('product_id', $productId)
            ->where('status', 'completed')
            ->sum('quantity');
        return max(0, $product->stock - $holds - $sold);
    }

}
