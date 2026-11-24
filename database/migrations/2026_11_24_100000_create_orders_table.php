<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quotation_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('rfq_id')->constrained()->cascadeOnDelete();
            $table->string('reference', 32)->unique();
            $table->string('status', 30)->default('Awaiting Payment')->index();
            $table->string('payment_status', 20)->default('Unpaid')->index();
            $table->string('customer_name', 100);
            $table->string('customer_email', 254);
            $table->string('customer_phone', 25);
            $table->string('delivery_location', 200);
            $table->date('requested_delivery_date')->nullable();
            $table->unsignedBigInteger('subtotal_centavos')->default(0);
            $table->unsignedBigInteger('delivery_centavos')->default(0);
            $table->unsignedBigInteger('total_centavos')->default(0);
            $table->unsignedBigInteger('paid_centavos')->default(0);
            $table->char('access_token_hash', 64)->unique();
            $table->text('access_token_encrypted');
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('packed_at')->nullable();
            $table->timestamp('dispatched_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancel_reason', 500)->nullable();
            $table->timestamps();

            $table->index(['status', 'id']);
            $table->index(['payment_status', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
