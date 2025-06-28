<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ChaletCustomPricing extends Model
{
    protected $table = 'chalet_custom_pricing';

    protected $fillable = [
        'chalet_id', 'time_slot_id', 'start_date', 'end_date',
        'custom_adjustment', 'name', 'is_active',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'is_active' => 'boolean',
        'custom_adjustment' => 'decimal:2',
    ];

    public function chalet(): BelongsTo
    {
        return $this->belongsTo(Chalet::class);
    }

    public function timeSlot(): BelongsTo
    {
        return $this->belongsTo(ChaletTimeSlot::class, 'time_slot_id');
    }
}
