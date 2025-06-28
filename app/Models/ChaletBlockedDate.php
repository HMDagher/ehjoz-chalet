<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\BlockReason;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

final class ChaletBlockedDate extends Model
{
    protected $fillable = [
        'chalet_id', 'date', 'time_slot_id', 'reason', 'notes',
    ];

    protected $casts = [
        'date' => 'date',
        'reason' => BlockReason::class,
    ];

    public function chalet(): BelongsTo
    {
        return $this->belongsTo(Chalet::class);
    }

    public function timeSlot(): BelongsTo
    {
        return $this->belongsTo(ChaletTimeSlot::class, 'time_slot_id');
    }

    protected static function booted(): void
    {
        static::saving(function (self $chaletBlockedDate) {
            if (auth()->check() && $userChalet = auth()->user()->chalet) {
                // If creating, set the chalet_id
                if (! $chaletBlockedDate->exists) {
                    $chaletBlockedDate->chalet_id = $userChalet->id;
                }

                // On both create and update, ensure the chalet_id belongs to the user
                if ($chaletBlockedDate->chalet_id !== $userChalet->id) {
                    abort(403, 'You can only manage blocked dates for your own chalet.');
                }
            }
        });
    }
}
