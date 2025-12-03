<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('holds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('quantity');
            $table->timestamp('expires_at');
            $table->enum('status', ['active', 'released', 'consumed'])->default('active');
            $table->timestamps();

            $table->index(['product_id', 'expires_at']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('holds');
    }
};
