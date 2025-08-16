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
        Schema::table('bookings', function (Blueprint $table) {
            $table->decimal('discount_amount', 10, 2)->default(0)->after('platform_commission');
            $table->integer('discount_percentage')->default(0)->after('discount_amount');
            $table->string('discount_reason')->nullable()->after('discount_percentage');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn(['discount_amount', 'discount_percentage', 'discount_reason']);
        });
    }
};
