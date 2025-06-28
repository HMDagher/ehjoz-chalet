<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chalet_custom_pricing', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chalet_id')->constrained('chalets');
            $table->foreignId('time_slot_id')->constrained('chalet_time_slots');
            $table->date('start_date');
            $table->date('end_date');
            $table->decimal('custom_price', 10, 2);
            $table->string('name', 100);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['chalet_id', 'start_date', 'end_date'], 'idx_chalet_dates');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chalet_custom_pricing');
    }
};
