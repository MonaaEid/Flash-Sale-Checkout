<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;


class Order extends Model
{
    use HasFactory;

    protected $fillable = ['product_id', 'quantity', 'status'];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public static function sold_qty(int $productId): int
    {
        return Order::where('product_id', $productId)
            ->where('status', 'completed')
            ->sum('quantity');
    }

}
