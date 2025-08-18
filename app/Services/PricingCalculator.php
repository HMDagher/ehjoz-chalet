<?php

namespace App\Services;

use App\Models\Chalet;
use App\Models\ChaletCustomPricing;
use App\Models\ChaletTimeSlot;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class PricingCalculator
{
    /**
     * Calculate total booking price
     */
    public function calculateBookingPrice(
        int $chaletId,
        array $timeSlotIds,
        string $startDate,
        ?string $endDate,
        string $bookingType
    ): array {
        try {
            // Get chalet for weekend days configuration
            $chalet = Chalet::find($chaletId);
            if (! $chalet) {
                throw new \Exception("Chalet not found: {$chaletId}");
            }

            // Get time slots
            $timeSlots = ChaletTimeSlot::whereIn('id', $timeSlotIds)
                ->where('chalet_id', $chaletId)
                ->where('is_active', true)
                ->get();

            if ($timeSlots->count() !== count($timeSlotIds)) {
                throw new \Exception('Some time slots not found or inactive');
            }

            // Calculate pricing based on booking type
            if ($bookingType === 'overnight') {
                return $this->calculateOvernightPricing($chalet, $timeSlots->first(), $startDate, $endDate);
            } else {
                return $this->calculateDayUsePricing($chalet, $timeSlots, $startDate);
            }

        } catch (\Exception $e) {
            Log::error('Pricing calculation failed', [
                'chalet_id' => $chaletId,
                'time_slot_ids' => $timeSlotIds,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'booking_type' => $bookingType,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Calculate pricing for overnight bookings
     * Each chalet has one overnight slot (is_overnight=true)
     * Price = overnight slot price per night × number of nights
     *
     * @param  ChaletTimeSlot  $timeSlot  (the overnight slot)
     */
    private function calculateOvernightPricing(Chalet $chalet, ChaletTimeSlot $timeSlot, string $startDate, string $endDate): array
    {
        // Ensure this is an overnight slot
        if (! $timeSlot->is_overnight) {
            throw new \Exception('Overnight booking requires an overnight time slot');
        }

        $dateRange = TimeSlotHelper::getDateRange($startDate, $endDate);
        $totalAmount = 0;
        $nightlyBreakdown = [];

        // Calculate price for each night using the overnight slot
        foreach ($dateRange as $date) {
            $nightPricing = $this->calculateSlotPriceForDate($chalet, $timeSlot, $date);
            $totalAmount += $nightPricing['final_price'];

            $nightlyBreakdown[] = [
                'date' => $date,
                'night_number' => array_search($date, $dateRange) + 1,
                'base_price' => $nightPricing['base_price'],
                'adjustment' => $nightPricing['adjustment'],
                'final_price' => $nightPricing['final_price'],
                'is_weekend' => $nightPricing['is_weekend'],
                'custom_pricing_applied' => ! empty($nightPricing['custom_pricing_id']),
            ];
        }

        $slotDetails = [
            'slot_id' => $timeSlot->id,
            'slot_name' => $timeSlot->name ?? ($timeSlot->start_time.' - '.$timeSlot->end_time),
            'is_overnight' => true,
            'nights' => count($dateRange),
            'total_price' => $totalAmount,
            'nightly_breakdown' => $nightlyBreakdown,
        ];

        return [
            'total_amount' => $totalAmount,
            'currency' => 'USD', // Make configurable
            'booking_type' => 'overnight',
            'nights_count' => count($dateRange),
            'slot_details' => [$slotDetails], // Array for consistency
            'breakdown_summary' => $this->generateBreakdownSummary([$slotDetails]),
        ];
    }

    /**
     * Calculate pricing for day-use bookings
     * Sum of individual slot prices (weekday_price/weekend_price + custom_adjustment)
     */
    private function calculateDayUsePricing(Chalet $chalet, Collection $timeSlots, string $date): array
    {
        $totalAmount = 0;
        $slotDetails = [];

        // Calculate price for each selected slot
        foreach ($timeSlots as $slot) {
            // Ensure this is not an overnight slot for day-use
            if ($slot->is_overnight) {
                throw new \Exception('Day-use booking cannot include overnight slots');
            }

            $slotPricing = $this->calculateSlotPriceForDate($chalet, $slot, $date);
            $totalAmount += $slotPricing['final_price'];

            $slotDetails[] = [
                'slot_id' => $slot->id,
                'slot_name' => $slot->name ?? ($slot->start_time.' - '.$slot->end_time),
                'is_overnight' => false,
                'base_price' => $slotPricing['base_price'],
                'adjustment' => $slotPricing['adjustment'],
                'total_price' => $slotPricing['final_price'],
                'is_weekend' => $slotPricing['is_weekend'],
                'custom_pricing_applied' => ! empty($slotPricing['custom_pricing_id']),
            ];
        }

        return [
            'total_amount' => $totalAmount,
            'currency' => 'USD', // Make configurable
            'booking_type' => 'day-use',
            'booking_date' => $date,
            'slots_count' => $timeSlots->count(),
            'slot_details' => $slotDetails,
            'breakdown_summary' => $this->generateBreakdownSummary($slotDetails),
        ];
    }

    /**
     * Calculate slot price for a specific date
     */
    private function calculateSlotPriceForDate(Chalet $chalet, ChaletTimeSlot $slot, string $date): array
    {
        // Determine if it's a weekend based on chalet configuration
        $isWeekend = $this->isWeekendDate($chalet, $date);

        // Get base price (weekday_price or weekend_price)
        $basePrice = $isWeekend ? $slot->weekend_price : $slot->weekday_price;

        // Check for custom pricing adjustments (custom_adjustment + base price)
        $customPricing = $this->getCustomPricingForDate($slot, $date);
        $rawAdjustment = $customPricing ? ($customPricing->custom_adjustment ?? ($customPricing->custom_price ?? 0)) : 0;
        $adjustment = max(0, (float) $rawAdjustment);

        // Calculate final price: base_price + custom_adjustment (ensure it doesn't go negative)
        $finalPrice = max(0, $basePrice + $adjustment);

        return [
            'base_price' => $basePrice,
            'adjustment' => $adjustment,
            'final_price' => $finalPrice,
            'is_weekend' => $isWeekend,
            'custom_pricing_id' => $customPricing?->id,
            'custom_pricing_name' => $customPricing?->name ?? null,
        ];
    }

    /**
     * Check if a date is considered weekend for the chalet
     */
    private function isWeekendDate(Chalet $chalet, string $date): bool
    {
        $dayOfWeekNumber = Carbon::createFromFormat('Y-m-d', $date)->dayOfWeek;
        // Default to Saturday (6) and Sunday (0) if not set
        $weekendDays = $chalet->weekend_days ?? [6, 0];
        // Ensure all values in weekend_days are integers for strict comparison
        $weekendDays = array_map('intval', $weekendDays);

        return in_array($dayOfWeekNumber, $weekendDays, true);
    }

    /**
     * Get custom pricing for a specific date and slot
     */
    private function getCustomPricingForDate(ChaletTimeSlot $slot, string $date): ?ChaletCustomPricing
    {
        return ChaletCustomPricing::where('chalet_id', $slot->chalet_id)
            ->where('time_slot_id', $slot->id)
            ->where('is_active', true)
            ->whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date)
            ->orderBy('created_at', 'desc') // Get most recent if multiple exist
            ->first();
    }

    /**
     * Generate pricing breakdown summary
     */
    private function generateBreakdownSummary(array $slotDetails): array
    {
        $summary = [
            'subtotal' => 0,
            'total_adjustments' => 0,
            'weekend_slots' => 0,
            'weekday_slots' => 0,
            'custom_pricing_applied' => false,
        ];

        foreach ($slotDetails as $slot) {
            if (isset($slot['nightly_breakdown'])) {
                // Overnight booking breakdown
                foreach ($slot['nightly_breakdown'] as $night) {
                    $summary['subtotal'] += $night['base_price'];
                    $summary['total_adjustments'] += $night['adjustment'];

                    if ($night['is_weekend']) {
                        $summary['weekend_slots']++;
                    } else {
                        $summary['weekday_slots']++;
                    }

                    if ($night['custom_pricing_applied']) {
                        $summary['custom_pricing_applied'] = true;
                    }
                }
            } else {
                // Day-use booking breakdown
                $summary['subtotal'] += $slot['base_price'];
                $summary['total_adjustments'] += $slot['adjustment'];

                if ($slot['is_weekend']) {
                    $summary['weekend_slots']++;
                } else {
                    $summary['weekday_slots']++;
                }

                if ($slot['custom_pricing_applied']) {
                    $summary['custom_pricing_applied'] = true;
                }
            }
        }

        $summary['final_total'] = $summary['subtotal'] + $summary['total_adjustments'];

        return $summary;
    }

    /**
     * Calculate estimated price for search results (quick calculation)
     */
    public function calculateEstimatedPrice(
        int $chaletId,
        array $availableSlots,
        string $startDate,
        ?string $endDate,
        string $bookingType
    ): array {
        try {
            $chalet = Chalet::find($chaletId);
            if (! $chalet || empty($availableSlots)) {
                return [
                    'min_price' => 0,
                    'max_price' => 0,
                    'estimated_total' => 0,
                ];
            }

            $prices = [];

            if ($bookingType === 'overnight') {
                // For overnight, calculate for the first available slot across date range
                $slot = (object) $availableSlots[0];
                $dateRange = TimeSlotHelper::getDateRange($startDate, $endDate ?? $startDate);

                foreach ($dateRange as $date) {
                    $isWeekend = $this->isWeekendDate($chalet, $date);
                    $basePrice = $isWeekend ? $slot->weekend_price : $slot->weekday_price;
                    $prices[] = $basePrice; // Simplified - not including custom pricing for estimates
                }
            } else {
                // For day-use, calculate for all available slots on the date
                foreach ($availableSlots as $slotData) {
                    $slot = (object) $slotData;
                    $isWeekend = $this->isWeekendDate($chalet, $startDate);
                    $basePrice = $isWeekend ? $slot->weekend_price : $slot->weekday_price;
                    $prices[] = $basePrice;
                }
            }

            return [
                'min_price' => ! empty($prices) ? min($prices) : 0,
                'max_price' => ! empty($prices) ? max($prices) : 0,
                'estimated_total' => ! empty($prices) ? array_sum($prices) : 0,
                'price_range' => $prices,
                'currency' => 'USD',
            ];

        } catch (\Exception $e) {
            Log::error('Estimated price calculation failed', [
                'chalet_id' => $chaletId,
                'error' => $e->getMessage(),
            ]);

            return [
                'min_price' => 0,
                'max_price' => 0,
                'estimated_total' => 0,
            ];
        }
    }

    /**
     * Apply discount or promotional pricing
     */
    public function applyDiscount(array $pricing, array $discountConfig): array
    {
        // This can be extended to handle promotional codes, loyalty discounts, etc.
        $discountAmount = 0;
        $discountType = $discountConfig['type'] ?? 'percentage';
        $discountValue = $discountConfig['value'] ?? 0;

        if ($discountType === 'percentage') {
            $discountAmount = ($pricing['total_amount'] * $discountValue) / 100;
        } elseif ($discountType === 'fixed') {
            $discountAmount = min($discountValue, $pricing['total_amount']);
        }

        $pricing['original_amount'] = $pricing['total_amount'];
        $pricing['discount_amount'] = $discountAmount;
        $pricing['total_amount'] = $pricing['total_amount'] - $discountAmount;
        $pricing['discount_applied'] = $discountConfig;

        return $pricing;
    }
}
