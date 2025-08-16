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
use Illuminate\Database\Eloquent\Factories\HasFactory;


final class Chalet extends Model implements HasMedia
{
    use InteractsWithMedia, HasFactory;

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('featured_image')
            ->singleFile();
        
        $this->addMediaCollection('gallery');
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumb')
            ->width(368)
            ->height(232)
            ->sharpen(10)
            ->nonQueued();

        $this->addMediaConversion('preview')
            ->width(750)
            ->height(500)
            ->sharpen(10)
            ->nonQueued();
    }

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
        'location',
        'weekend_days',
    ];

    protected $appends = [
        'location',
    ];

    /**
    * Returns the 'latitude' and 'longitude' attributes as the computed 'location' attribute,
    * as a standard Google Maps style Point array with 'lat' and 'lng' attributes.
    *
    * Used by the Filament Google Maps package.
    *
    * Requires the 'location' attribute be included in this model's $fillable array.
    *
    * @return array
    */

    public function getLocationAttribute(): array
    {
        return [
            "lat" => (float)$this->latitude,
            "lng" => (float)$this->longitude,
        ];
    }

    /**
    * Takes a Google style Point array of 'lat' and 'lng' values and assigns them to the
    * 'latitude' and 'longitude' attributes on this model.
    *
    * Used by the Filament Google Maps package.
    *
    * Requires the 'location' attribute be included in this model's $fillable array.
    *
    * @param ?array $location
    * @return void
    */
    public function setLocationAttribute(?array $location): void
    {
        if (is_array($location))
        {
            $this->attributes['latitude'] = $location['lat'];
            $this->attributes['longitude'] = $location['lng'];
            unset($this->attributes['location']);
        }
    }

    /**
     * Get the lat and lng attribute/field names used on this table
     *
     * Used by the Filament Google Maps package.
     *
     * @return string[]
     */
    public static function getLatLngAttributes(): array
    {
        return [
            'lat' => 'latitude',
            'lng' => 'longitude',
        ];
    }

   /**
    * Get the name of the computed location attribute
    *
    * Used by the Filament Google Maps package.
    *
    * @return string
    */
    public static function getComputedLocation(): string
    {
        return 'location';
    }

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
            'weekend_days' => 'array',
        ];
    }
}
