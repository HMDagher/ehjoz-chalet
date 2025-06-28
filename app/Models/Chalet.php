<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ChaletStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

final class Chalet extends Model implements HasMedia
{
    use InteractsWithMedia;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'owner_id',
        'name',
        'slug',
        'description',
        'address',
        'city',
        'latitude',
        'longitude',
        'max_adults',
        'max_children',
        'bedrooms_count',
        'bathrooms_count',
        'check_in_instructions',
        'house_rules',
        'cancellation_policy',
        'status',
        'is_featured',
        'featured_until',
        'meta_title',
        'meta_description',
        'facebook_url',
        'instagram_url',
        'website_url',
        'whatsapp_number',
        'average_rating',
        'total_reviews',
        'total_earnings',
        'pending_earnings',
        'total_withdrawn',
        'bank_name',
        'account_holder_name',
        'account_number',
        'iban',
    ];

    /**
     * The amenities that belong to the chalet.
     */
    public function amenities()
    {
        return $this->belongsToMany(Amenity::class, 'amenity_chalet');
    }

    /**
     * The facilities that belong to the chalet.
     */
    public function facilities()
    {
        return $this->belongsToMany(Facility::class, 'chalet_facility');
    }

    /**
     * Get the settlements for the chalet.
     */
    public function settlements()
    {
        return $this->hasMany(Settlement::class);
    }

    /**
     * Get the reviews for the chalet.
     */
    public function reviews()
    {
        return $this->hasMany(Review::class);
    }

    /**
     * The time slots for the chalet.
     */
    public function timeSlots()
    {
        return $this->hasMany(ChaletTimeSlot::class);
    }

    /**
     * The blocked dates for the chalet.
     */
    public function blockedDates()
    {
        return $this->hasMany(ChaletBlockedDate::class);
    }

    /**
     * The custom pricing for the chalet.
     */
    public function customPricing()
    {
        return $this->hasMany(ChaletCustomPricing::class);
    }

    /**
     * The Bookings for the chalet.
     */
    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }

    /**
     * Get all of the customers who have booked the chalet.
     */
    public function customers()
    {
        return $this->hasManyThrough(User::class, Booking::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:8',
            'longitude' => 'decimal:8',
            'is_featured' => 'boolean',
            'featured_until' => 'timestamp',
            'average_rating' => 'decimal:2',
            'total_earnings' => 'decimal:2',
            'pending_earnings' => 'decimal:2',
            'total_withdrawn' => 'decimal:2',
            'status' => ChaletStatus::class,
        ];
    }
}
