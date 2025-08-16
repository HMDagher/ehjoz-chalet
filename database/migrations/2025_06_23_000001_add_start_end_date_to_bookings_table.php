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

        // Drop old columns and foreign keys if they exist
        Schema::table('bookings', function (Blueprint $table) {
            $connection = Schema::getConnection();

            // Drop all problematic indexes first if they exist
            $indexesToDrop = [
                'bookings_chalet_id_booking_date_chalet_time_slot_id_index',
                'bookings_booking_date_index',
                'bookings_status_index',
                'bookings_payment_status_index',
            ];

            foreach ($indexesToDrop as $indexName) {
                if ($connection->getDriverName() === 'sqlite') {
                    // For SQLite, check if index exists
                    $indexExists = $connection->select("SELECT name FROM sqlite_master WHERE type='index' AND name=?", [$indexName]);
                    if (! empty($indexExists)) {
                        $connection->statement("DROP INDEX IF EXISTS {$indexName}");
                    }
                } elseif ($connection->getDriverName() === 'pgsql') {
                    // For PostgreSQL
                    $indexExists = $connection->select("SELECT indexname FROM pg_indexes WHERE tablename = 'bookings' AND indexname = ?", [$indexName]);
                    if (! empty($indexExists)) {
                        $connection->statement("DROP INDEX IF EXISTS {$indexName}");
                    }
                } else {
                    // For MySQL
                    $dbName = $connection->getDatabaseName();
                    $indexExists = $connection->select("SELECT * FROM information_schema.statistics WHERE table_schema = ? AND table_name = 'bookings' AND index_name = ?", [$dbName, $indexName]);
                    if (! empty($indexExists)) {
                        $table->dropIndex($indexName);
                    }
                }
            }

            // Drop foreign key if it exists
            if (Schema::hasColumn('bookings', 'chalet_time_slot_id')) {
                $table->dropForeign(['chalet_time_slot_id']);
            }

            // Drop columns if they exist
            $columnsToCheck = ['chalet_time_slot_id', 'booking_date', 'start_time', 'end_time'];
            foreach ($columnsToCheck as $column) {
                if (Schema::hasColumn('bookings', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
