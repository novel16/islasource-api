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
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('category', 30);
            $table->string('unit', 10);
            $table->string('status', 20);
            $table->text('description');
            $table->text('specs')->nullable();
            $table->string('slug', 160)->index();
            $table->string('meta_title', 70);
            $table->string('meta_description', 160);
            $table->string('og_title', 100)->nullable();
            $table->string('og_description', 200)->nullable();
            $table->string('image')->default('/images/squid.jpg');
            $table->string('image_alt');
            $table->string('og_image')->nullable();
            $table->text('canonical_url')->nullable();
            $table->boolean('indexable')->default(true);
            $table->decimal('min_order_quantity', 10, 3)->default('1.000');
            $table->string('availability_note', 500)->nullable()->default('Sourced on request. Availability and final pricing are confirmed after your quote request.');
            $table->timestamps();
            $table->softDeletes();
            $table->index(['deleted_at', 'id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
