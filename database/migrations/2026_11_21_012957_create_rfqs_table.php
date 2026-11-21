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
        Schema::create('rfqs', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 32)->unique();
            $table->string('customer_name', 100);
            $table->string('customer_email', 254);
            $table->string('customer_phone', 25);
            $table->string('delivery_location', 200);
            $table->date('requested_delivery_date')->nullable();
            $table->text('customer_notes')->nullable();
            $table->string('status', 20)->default('New')->index();
            $table->timestamps();
            $table->index(['created_at', 'id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rfqs');
    }
};
