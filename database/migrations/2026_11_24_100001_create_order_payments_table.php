<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 20)->default('paymongo');
            $table->string('payment_method', 20)->default('qrph');
            $table->string('status', 20)->default('Pending')->index();
            $table->unsignedBigInteger('amount_centavos');
            $table->string('paymongo_checkout_session_id', 100)->nullable()->unique();
            $table->string('paymongo_payment_id', 100)->nullable()->unique();
            $table->string('paymongo_payment_intent_id', 100)->nullable();
            $table->text('checkout_url')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->string('failure_reason', 500)->nullable();
            $table->timestamps();

            $table->index(['order_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_payments');
    }
};
