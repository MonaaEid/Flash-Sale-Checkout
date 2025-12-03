<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class Hold extends Model
{
    use HasFactory;

    protected $fillable = ['product_id', 'quantity', 'expires_at', 'status'];

    protected $dates = ['expires_at'];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active')->where('expires_at', '>', now());
    }
}
