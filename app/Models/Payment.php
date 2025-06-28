<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class Payment extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'booking_id',
        'payment_reference',
        'amount',
        'payment_method',
        'paid_at',
        'status',
        'notes',
    ];

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the chalet for the payment.
     */
    public function chalet()
    {
        return $this->hasOneThrough(Chalet::class, Booking::class, 'id', 'id', 'booking_id', 'chalet_id');
    }

    /**
     * Get the customer for the payment.
     */
    public function customer()
    {
        return $this->hasOneThrough(User::class, Booking::class, 'id', 'id', 'booking_id', 'user_id');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'paid_at' => 'timestamp',
            'payment_method' => PaymentMethod::class,
            'status' => PaymentStatus::class,
        ];
    }
}
