<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class Review extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'booking_id',
        'user_id',
        'chalet_id',
        'overall_rating',
        'cleanliness_rating',
        'location_rating',
        'value_rating',
        'communication_rating',
        'comment',
        'is_approved',
    ];

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function chalet(): BelongsTo
    {
        return $this->belongsTo(Chalet::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'overall_rating' => 'integer',
            'cleanliness_rating' => 'integer',
            'location_rating' => 'integer',
            'value_rating' => 'integer',
            'communication_rating' => 'integer',
            'is_approved' => 'boolean',
        ];
    }
}
