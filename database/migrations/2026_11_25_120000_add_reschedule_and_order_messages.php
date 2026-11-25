<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rfqs', function (Blueprint $table): void {
            $table->date('proposed_available_date')->nullable()->after('requested_delivery_date');
            $table->date('proposed_delivery_date')->nullable()->after('proposed_available_date');
            $table->text('schedule_notice')->nullable()->after('customer_notes');
            $table->string('schedule_status', 24)->nullable()->after('status');
            $table->timestamp('schedule_responded_at')->nullable()->after('schedule_status');
            $table->string('schedule_customer_note', 500)->nullable()->after('schedule_responded_at');
            $table->string('schedule_token_hash', 64)->nullable()->unique()->after('schedule_customer_note');
            $table->text('schedule_token_encrypted')->nullable()->after('schedule_token_hash');
        });

        Schema::table('quotations', function (Blueprint $table): void {
            $table->date('confirmed_delivery_date')->nullable()->after('balance_centavos');
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->date('confirmed_delivery_date')->nullable()->after('requested_delivery_date');
        });

        Schema::create('order_messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('sender_type', 16);
            $table->foreignId('sender_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('sender_name', 100);
            $table->text('body');
            $table->timestamps();
            $table->index(['order_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_messages');

        Schema::table('orders', function (Blueprint $table): void {
            $table->dropColumn('confirmed_delivery_date');
        });

        Schema::table('quotations', function (Blueprint $table): void {
            $table->dropColumn('confirmed_delivery_date');
        });

        Schema::table('rfqs', function (Blueprint $table): void {
            $table->dropUnique(['schedule_token_hash']);
            $table->dropColumn([
                'proposed_available_date',
                'proposed_delivery_date',
                'schedule_notice',
                'schedule_status',
                'schedule_responded_at',
                'schedule_customer_note',
                'schedule_token_hash',
                'schedule_token_encrypted',
            ]);
        });
    }
};
