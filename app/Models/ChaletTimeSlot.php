<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

final class ChaletTimeSlot extends Model
{
    use HasFactory;
    protected $fillable = [
        'chalet_id', 'name', 'start_time', 'end_time', 'is_overnight',
        'duration_hours', 'weekday_price', 'weekend_price',
        'allows_extra_hours', 'extra_hour_price', 'max_extra_hours',
        'available_days', 'is_active',
    ];

    protected $casts = [
        'available_days' => 'array',
        'is_overnight' => 'boolean',
        'allows_extra_hours' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function chalet(): BelongsTo
    {
        return $this->belongsTo(Chalet::class);
    }

    public function bookings(): BelongsToMany
    {
        return $this->belongsToMany(Booking::class, 'booking_time_slot');
    }
}
