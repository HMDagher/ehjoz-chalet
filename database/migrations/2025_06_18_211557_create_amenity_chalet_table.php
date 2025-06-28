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
        Schema::create('amenity_chalet', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chalet_id')->constrained('chalets');
            $table->foreignId('amenity_id')->constrained('amenities');
            $table->timestamps();

            $table->unique(['chalet_id', 'amenity_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('amenity_chalet');
    }
};
