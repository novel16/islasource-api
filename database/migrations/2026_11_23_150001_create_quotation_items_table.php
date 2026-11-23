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
        Schema::create('quotation_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quotation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('rfq_item_id')->nullable()->constrained()->nullOnDelete();
            $table->string('product_name', 100);
            $table->string('unit', 10);
            $table->decimal('quantity', 10, 3);
            $table->string('specs', 500)->nullable();
            $table->boolean('is_available')->default(true);
            $table->unsignedBigInteger('unit_cost_centavos')->default(0);
            $table->unsignedBigInteger('line_cost_centavos')->default(0);
            $table->unsignedBigInteger('line_selling_centavos')->default(0);
            $table->timestamps();
            $table->index(['quotation_id', 'id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quotation_items');
    }
};
