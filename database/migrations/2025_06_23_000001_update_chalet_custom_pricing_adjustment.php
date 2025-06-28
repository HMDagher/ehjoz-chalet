<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chalet_custom_pricing', function (Blueprint $table) {
            $table->dropColumn('custom_price');
            $table->decimal('custom_adjustment', 10, 2)->nullable()->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('chalet_custom_pricing', function (Blueprint $table) {
            $table->dropColumn('custom_adjustment');
            $table->decimal('custom_price', 10, 2)->nullable()->default(0);
        });
    }
};
