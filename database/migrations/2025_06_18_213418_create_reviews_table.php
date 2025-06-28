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
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chalet_id')->constrained('chalets');
            $table->foreignId('user_id')->constrained('users');
            $table->foreignId('booking_id')->constrained('bookings');
            $table->tinyInteger('overall_rating')->index();
            $table->tinyInteger('cleanliness_rating')->nullable();
            $table->tinyInteger('location_rating')->nullable();
            $table->tinyInteger('value_rating')->nullable();
            $table->tinyInteger('communication_rating')->nullable();
            $table->text('comment')->nullable();
            $table->boolean('is_approved')->index()->nullable()->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};
