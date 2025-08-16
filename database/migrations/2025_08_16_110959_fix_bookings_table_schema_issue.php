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
        // Check if the problematic index exists and drop it
        if (Schema::hasTable('bookings')) {
            Schema::table('bookings', function (Blueprint $table) {
                // Drop the problematic index if it exists
                $indexName = 'bookings_chalet_id_booking_date_chalet_time_slot_id_index';
                
                // Check if index exists using raw SQL since Laravel doesn't have a direct method
                $connection = Schema::getConnection();
                $dbName = $connection->getDatabaseName();
                
                if ($connection->getDriverName() === 'sqlite') {
                    // For SQLite, check if index exists
                    $indexExists = $connection->select("SELECT name FROM sqlite_master WHERE type='index' AND name=?", [$indexName]);
                    if (!empty($indexExists)) {
                        $connection->statement("DROP INDEX IF EXISTS {$indexName}");
                    }
                } elseif ($connection->getDriverName() === 'pgsql') {
                    // For PostgreSQL
                    $indexExists = $connection->select("SELECT indexname FROM pg_indexes WHERE tablename = 'bookings' AND indexname = ?", [$indexName]);
                    if (!empty($indexExists)) {
                        $connection->statement("DROP INDEX IF EXISTS {$indexName}");
                    }
                } else {
                    // For MySQL
                    $indexExists = $connection->select("SELECT * FROM information_schema.statistics WHERE table_schema = ? AND table_name = 'bookings' AND index_name = ?", [$dbName, $indexName]);
                    if (!empty($indexExists)) {
                        $table->dropIndex($indexName);
                    }
                }
                
                // Drop the chalet_time_slot_id column if it still exists
                if (Schema::hasColumn('bookings', 'chalet_time_slot_id')) {
                    $table->dropForeign(['chalet_time_slot_id']);
                    $table->dropColumn('chalet_time_slot_id');
                }
                
                // Drop other old columns if they still exist
                $columnsToCheck = ['booking_date', 'start_time', 'end_time'];
                foreach ($columnsToCheck as $column) {
                    if (Schema::hasColumn('bookings', $column)) {
                        $table->dropColumn($column);
                    }
                }
                
                // Ensure new columns exist
                if (!Schema::hasColumn('bookings', 'start_date')) {
                    $table->dateTime('start_date')->after('booking_reference');
                }
                
                if (!Schema::hasColumn('bookings', 'end_date')) {
                    $table->dateTime('end_date')->nullable()->after('start_date');
                }
                
                if (!Schema::hasColumn('bookings', 'booking_type')) {
                    $table->string('booking_type')->after('end_date')->default('day-use');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // This migration is a fix, so we don't need to reverse it
        // The original problematic migration should handle the reverse
    }
};
