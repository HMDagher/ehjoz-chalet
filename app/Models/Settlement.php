<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SettlementStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class Settlement extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'chalet_id',
        'settlement_reference',
        'period_start',
        'period_end',
        'total_bookings',
        'gross_amount',
        'commission_amount',
        'net_amount',
        'status',
        'paid_at',
        'payment_reference',
        'notes',
    ];

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
            'period_start' => 'date',
            'period_end' => 'date',
            'gross_amount' => 'decimal:2',
            'commission_amount' => 'decimal:2',
            'net_amount' => 'decimal:2',
            'paid_at' => 'timestamp',
            'status' => SettlementStatus::class,
        ];
    }
}
