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
        Schema::create('chalets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->constrained('users');
            $table->string('name', 255);
            $table->string('slug', 255)->unique();
            $table->text('description')->nullable();
            $table->text('address')->nullable();
            $table->string('city', 100)->nullable()->index();
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->integer('max_adults')->nullable();
            $table->integer('max_children')->nullable();
            $table->integer('bedrooms_count')->nullable();
            $table->integer('bathrooms_count')->nullable();
            $table->text('check_in_instructions')->nullable();
            $table->text('house_rules')->nullable();
            $table->text('cancellation_policy')->nullable();
            $table->string('status')->index()->default('active');
            $table->boolean('is_featured')->index()->nullable()->default(false);
            $table->timestamp('featured_until')->nullable();
            $table->string('meta_title', 255)->nullable();
            $table->text('meta_description')->nullable();
            $table->string('facebook_url', 500)->nullable();
            $table->string('instagram_url', 500)->nullable();
            $table->string('website_url', 500)->nullable();
            $table->string('whatsapp_number', 20)->nullable();
            $table->decimal('average_rating', 3, 2)->nullable()->default(0);
            $table->integer('total_reviews')->nullable();
            $table->decimal('total_earnings', 10, 2)->nullable()->default(0);
            $table->decimal('pending_earnings', 10, 2)->nullable()->default(0);
            $table->decimal('total_withdrawn', 10, 2)->nullable()->default(0);
            $table->string('bank_name', 100)->nullable();
            $table->string('account_holder_name', 100)->nullable();
            $table->string('account_number', 50)->nullable();
            $table->string('iban', 50)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chalets');
    }
};
