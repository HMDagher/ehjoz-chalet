<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chalet_blocked_dates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chalet_id')->constrained('chalets');
            $table->date('date');
            $table->foreignId('time_slot_id')->nullable()->constrained('chalet_time_slots');
            $table->string('reason')->default('booked_elsewhere');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['chalet_id', 'date', 'time_slot_id'], 'unique_chalet_date_slot');
            $table->index(['chalet_id', 'date'], 'idx_chalet_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chalet_blocked_dates');
    }
};
