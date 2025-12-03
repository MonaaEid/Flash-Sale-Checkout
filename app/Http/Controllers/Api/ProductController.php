<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ProductController extends Controller
{
    /**
     * Return product basic fields and accurate available stock.
     */
    public function show($id)
    {
        $cacheKey = "product:{$id}:available_stock";
        $calculated = Cache::remember($cacheKey, now()->addSeconds(1), function () use ($id) {
            $product = DB::table('products')->where('id', $id)->first();
            if (!$product) {
                return null;
            }

            $holds = DB::table('holds')
                ->where('product_id', $id)
                ->where('status', 'active')
                ->where('expires_at', '>', now())
                ->sum('quantity');

            $sold = Order::sold_qty($id);

            return max(0, $product->stock - $holds - $sold);
        });

        if (is_null($calculated)) {
            return response()->json(['message' => 'Product not found'], 404);
        }

        $product = Product::find($id);

        return response()->json([
            'id' => $product->id,
            'name' => $product->name,
            'price' => (string) $product->price,
            'stock' => (int) $product->stock,
            'available_stock' => (int) $calculated,
        ]);
    }
}
