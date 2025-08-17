<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            // Add new columns if they don't exist
            if (! Schema::hasColumn('bookings', 'start_date')) {
                $table->dateTime('start_date')->after('booking_reference');
            }
            if (! Schema::hasColumn('bookings', 'end_date')) {
                $table->dateTime('end_date')->nullable()->after('start_date');
            }
        });
    }
};
