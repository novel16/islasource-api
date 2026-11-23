<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('quotations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rfq_id')->constrained()->cascadeOnDelete();
            $table->string('reference', 32)->unique();
            $table->unsignedSmallInteger('version');
            $table->string('status', 20)->default('Draft')->index();
            $table->unsignedBigInteger('seafood_cost_centavos')->default(0);
            $table->unsignedBigInteger('handling_centavos')->default(0);
            $table->decimal('markup_percent', 6, 2)->default('20.00');
            $table->unsignedBigInteger('markup_centavos')->default(0);
            $table->unsignedBigInteger('subtotal_centavos')->default(0);
            $table->unsignedBigInteger('delivery_centavos')->default(0);
            $table->unsignedBigInteger('total_centavos')->default(0);
            $table->unsignedTinyInteger('deposit_percent')->default(50);
            $table->unsignedBigInteger('deposit_centavos')->default(0);
            $table->unsignedBigInteger('balance_centavos')->default(0);
            $table->unsignedSmallInteger('validity_hours')->default(48);
            $table->timestamp('valid_until')->nullable();
            $table->text('terms');
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('declined_at')->nullable();
            $table->string('decline_reason', 500)->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancel_reason', 500)->nullable();
            $table->char('access_token_hash', 64)->nullable()->unique();
            $table->text('access_token_encrypted')->nullable();
            $table->timestamp('last_emailed_at')->nullable();
            $table->string('last_email_error', 500)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['rfq_id', 'version']);
            $table->index(['status', 'id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quotations');
    }
};
