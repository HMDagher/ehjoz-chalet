<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

final class Booking extends Model
{
    use HasFactory;
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'chalet_id',
        'user_id',
        'booking_reference',
        'start_date',
        'end_date',
        'booking_type',
        'extra_hours',
        'adults_count',
        'children_count',
        'total_guests',
        'base_slot_price',
        'seasonal_adjustment',
        'extra_hours_amount',
        'platform_commission',
        'discount_amount',
        'discount_percentage',
        'discount_reason',
        'total_amount',
        'status',
        'payment_status',
        'special_requests',
        'internal_notes',
        'cancellation_reason',
        'cancelled_at',
        'auto_completed_at',
        'owner_earning',
        'platform_earning',
        'remaining_payment',
    ];

    /**
     * Get the user that owns the booking.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the payment for the booking.
     */
    public function payment()
    {
        return $this->hasOne(Payment::class);
    }

    /**
     * Get the review for the booking.
     */
    public function review()
    {
        return $this->hasOne(Review::class);
    }

    public function chalet(): BelongsTo
    {
        return $this->belongsTo(Chalet::class);
    }

    /**
     * Get the chalet's owner.
     */
    public function chaletOwner()
    {
        return $this->hasOneThrough(
            User::class,
            Chalet::class,
            'id', // Local key on the chalets table.
            'id', // Local key on the users table.
            'chalet_id', // Foreign key on the bookings table.
            'owner_id'  // Foreign key on the chalets table.
        );
    }

    public function timeSlots()
    {
        return $this->belongsToMany(ChaletTimeSlot::class, 'booking_time_slot');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'start_date' => 'datetime',
            'end_date' => 'datetime',
            'base_slot_price' => 'decimal:2',
            'seasonal_adjustment' => 'decimal:2',
            'extra_hours_amount' => 'decimal:2',
            'platform_commission' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'discount_percentage' => 'integer',
            'total_amount' => 'decimal:2',
            'cancelled_at' => 'timestamp',
            'auto_completed_at' => 'timestamp',
            'status' => BookingStatus::class,
            'payment_status' => PaymentStatus::class,
            'owner_earning' => 'decimal:2',
            'platform_earning' => 'decimal:2',
            'remaining_payment' => 'decimal:2',
        ];
    }
}
