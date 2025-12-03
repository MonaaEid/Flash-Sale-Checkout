<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('idempotency_key')->nullable()->unique();
            $table->string('payment_id')->nullable()->unique();
            $table->foreignId('hold_id')->nullable()->constrained('holds')->nullOnDelete();
        });
    }

    public function down()
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropUnique(['idempotency_key']);
            $table->dropUnique(['payment_id']);
            $table->dropColumn(['idempotency_key','payment_id']);
            $table->dropForeign(['hold_id']);
            $table->dropColumn('hold_id');
        });
    }
};
