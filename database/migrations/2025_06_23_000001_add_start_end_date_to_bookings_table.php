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
            $table->dateTime('start_date')->after('booking_reference');
            $table->dateTime('end_date')->nullable()->after('start_date');
            $table->dropForeign(['chalet_time_slot_id']);
            $table->dropColumn([
                'chalet_time_slot_id',
                'booking_date',
                'start_time',
                'end_time'
            ]);
        });
    }
};
