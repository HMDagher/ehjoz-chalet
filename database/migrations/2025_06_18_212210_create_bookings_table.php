<?php

declare(strict_types=1);

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
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chalet_id')->constrained('chalets');
            $table->foreignId('user_id')->constrained('users');
            $table->foreignId('chalet_time_slot_id')->constrained('chalet_time_slots');
            $table->string('booking_reference')->unique()->index();
            $table->date('booking_date')->index();
            $table->time('start_time');
            $table->time('end_time');
            $table->integer('extra_hours')->nullable();
            $table->integer('adults_count')->default(1);
            $table->integer('children_count');
            $table->integer('total_guests')->default(1);
            $table->decimal('base_slot_price', 10, 2);
            $table->decimal('seasonal_adjustment', 10, 2)->nullable()->default(0);
            $table->decimal('extra_hours_amount', 10, 2)->nullable()->default(0);
            $table->decimal('platform_commission', 10, 2);
            $table->decimal('total_amount', 10, 2);
            $table->string('status')->index()->nullable()->default('pending');
            $table->string('payment_status')->index()->nullable()->default('pending');
            $table->text('special_requests')->nullable();
            $table->text('internal_notes')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('auto_completed_at')->nullable();
            $table->timestamps();

            $table->index(['chalet_id', 'booking_date', 'chalet_time_slot_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
