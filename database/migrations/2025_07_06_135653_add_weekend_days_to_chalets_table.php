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
        Schema::table('chalets', function (Blueprint $table) {
            $table->json('weekend_days')->default(json_encode([5, 6, 0]));
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('chalets', function (Blueprint $table) {
            $table->dropColumn('weekend_days');
        });
    }
};
