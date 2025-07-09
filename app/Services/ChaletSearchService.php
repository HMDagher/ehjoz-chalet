<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Chalet;
use Carbon\Carbon;

final class ChaletSearchService
{
    /**
     * Search for available chalets based on booking type and dates
     * 
     * @param string $startDate Check-in date (Y-m-d)
     * @param string|null $endDate Check-out date (Y-m-d), null for day-use
     * @param string $bookingType 'day-use' or 'overnight'
     * @return array Array of available chalets with their slots and pricing
     */
    public function searchChalets(string $startDate, ?string $endDate = null, string $bookingType = 'overnight'): array
    {
        // Standardize dates
        $startDate = Carbon::parse($startDate)->format('Y-m-d');
        
        // For day-use, we don't need an end date (or set it equal to start date)
        if ($bookingType === 'day-use') {
            $endDate = $startDate;
        } elseif (!$endDate) {
            // For overnight bookings with no end date, default to next day
            $endDate = Carbon::parse($startDate)->addDay()->format('Y-m-d');
        } else {
            $endDate = Carbon::parse($endDate)->format('Y-m-d');
        }
        
        // Get active chalets with all time slots
        $chalets = Chalet::where('status', 'active')
            ->with(['timeSlots' => function($query) {
                $query->where('is_active', true);
            }])
            ->get();
            
        \Log::info('SearchService: chalets loaded', [
            'count' => $chalets->count(),
            'ids' => $chalets->pluck('id')->toArray(),
        ]);

        $results = [];

        foreach ($chalets as $chalet) {
            // Skip chalets with no active time slots
            if ($chalet->timeSlots->isEmpty()) {
                \Log::info('SearchService: chalet has no active time slots', ['chalet_id' => $chalet->id]);
                continue;
            }

            $availabilityChecker = new \App\Services\ChaletAvailabilityChecker($chalet);
            $allSlots = [];
            $hasAvailableSlot = false;

            if ($bookingType === 'day-use') {
                // Get all day-use slots and mark their availability
                $dayUseSlots = $chalet->timeSlots
                    ->where('is_overnight', false)
                    ->map(function($slot) use ($availabilityChecker, $startDate, &$hasAvailableSlot) {
                        $price = $availabilityChecker->calculateDayUsePrice($startDate, $slot->id);
                        if ($price !== null && $price !== -1.0) {
                            $hasAvailableSlot = true;
                        }
                        return [
                            'id' => $slot->id,
                            'name' => $slot->name,
                            'start_time' => $slot->start_time,
                            'end_time' => $slot->end_time,
                            'duration_hours' => $slot->duration_hours,
                            'price' => ($price === -1.0 ? null : $price),
                            'booking_type' => 'day-use'
                        ];
                    })
                    ->values()
                    ->toArray();

                // Sort by availability (available first)
                usort($dayUseSlots, function($a, $b) {
                    return $b['price'] <=> $a['price'];
                });

                $allSlots = $dayUseSlots;

                // Calculate min price from available slots only
                $availableSlots = array_filter($dayUseSlots, function($slot) {
                    return array_key_exists('price', $slot) && $slot['price'] !== null;
                });
                $minPrice = !empty($availableSlots) ? min(array_column($availableSlots, 'price')) : null;

                // Only include chalets with at least one available slot
                if ($hasAvailableSlot) {
                    $results[] = [
                        'chalet' => $this->formatChaletData($chalet),
                        'slots' => $allSlots,
                        'min_price' => $minPrice,
                        'booking_type' => 'day-use'
                    ];
                }
            } else {
                // Get all overnight slots and mark their availability
                $overnightSlots = $chalet->timeSlots
                    ->where('is_overnight', true)
                    ->map(function($slot) use ($availabilityChecker, $startDate, $endDate, &$hasAvailableSlot) {
                        $priceData = $availabilityChecker->calculateOvernightPrice($startDate, $endDate, $slot->id);
                        $nights = $priceData['nights'] ?? 1;
                        $originalTotal = $priceData['original_price'] ?? $priceData['total_price'];
                        $originalPerNight = $nights > 0 ? $originalTotal / $nights : $originalTotal;
                        if ($originalPerNight !== null) {
                            $hasAvailableSlot = true;
                        }
                        return [
                            'id' => $slot->id,
                            'name' => $slot->name,
                            'start_time' => $slot->start_time,
                            'end_time' => $slot->end_time,
                            'duration_hours' => $slot->duration_hours,
                            'price_per_night' => $originalPerNight,
                            'total_price' => $originalTotal,
                            'nights' => $nights,
                            'booking_type' => 'overnight'
                        ];
                    })
                    ->values()
                    ->toArray();

                // Sort by availability (available first)
                usort($overnightSlots, function($a, $b) {
                    return $b['price_per_night'] <=> $a['price_per_night'];
                });

                $allSlots = $overnightSlots;

                // Calculate min price from available slots only
                $availableSlots = array_filter($overnightSlots, function($slot) {
                    return array_key_exists('price_per_night', $slot) && $slot['price_per_night'] !== null;
                });
                $minPerNightPrice = !empty($availableSlots) ? min(array_column($availableSlots, 'price_per_night')) : null;
                $nights = Carbon::parse($startDate)->diffInDays(Carbon::parse($endDate));
                $nights = max(1, $nights);

                // Only include chalets with at least one available slot
                if ($hasAvailableSlot) {
                    $results[] = [
                        'chalet' => $this->formatChaletData($chalet),
                        'slots' => $allSlots,
                        'min_price' => $minPerNightPrice,
                        'min_total_price' => $minPerNightPrice ? $minPerNightPrice * $nights : null,
                        'nights' => $nights,
                        'booking_type' => 'overnight'
                    ];
                }
            }
        }

        // Sort by min_price (ascending) for available chalets
        if (!empty($results)) {
            usort($results, function($a, $b) {
                // If either doesn't have a min_price (no available slots), put it at the end
                if ($a['min_price'] === null && $b['min_price'] === null) return 0;
                if ($a['min_price'] === null) return 1;
                if ($b['min_price'] === null) return -1;
                
                return $a['min_price'] <=> $b['min_price'];
            });
        }
        
        \Log::info('SearchService: final results', [
            'count' => count($results),
            'ids' => array_map(fn($r) => $r['chalet']['id'] ?? null, $results),
        ]);

        return $results;
    }

    /**
     * Get all available chalets without search filters
     * Used for initial page load to show all chalets
     * 
     * @return array Array of chalets with basic slot information
     */
    public function getAllChalets(): array
    {
        $today = Carbon::today();
        $tomorrow = Carbon::tomorrow();

        $chalets = Chalet::where('status', 'active')
            ->with(['timeSlots' => function($query) {
                $query->where('is_active', true);
            }])
            ->get();

        $results = [];

        foreach ($chalets as $chalet) {
            $allSlots = [];

            // Show all day-use slots with base price for today
            $dayUseSlots = $chalet->timeSlots
                ->where('is_active', true)
                ->where('is_overnight', false)
                ->map(function($slot) use ($today) {
                    // Use weekend/weekday price for today
                    $weekendDays = $slot->chalet->weekend_days ?? [5, 6, 0];
                    $isWeekend = in_array($today->dayOfWeek, $weekendDays);
                    $basePrice = $isWeekend ? $slot->weekend_price : $slot->weekday_price;
                    return [
                        'id' => $slot->id,
                        'name' => $slot->name,
                        'start_time' => $slot->start_time,
                        'end_time' => $slot->end_time,
                        'duration_hours' => $slot->duration_hours,
                        'price' => $basePrice,
                        'booking_type' => 'day-use'
                    ];
                })
                ->values()
                ->toArray();

            // Show all overnight slots with base price for today/tomorrow
            $overnightSlots = $chalet->timeSlots
                ->where('is_active', true)
                ->where('is_overnight', true)
                ->map(function($slot) use ($today, $tomorrow) {
                    $weekendDays = $slot->chalet->weekend_days ?? [5, 6, 0];
                    $isWeekend = in_array($today->dayOfWeek, $weekendDays);
                    $basePrice = $isWeekend ? $slot->weekend_price : $slot->weekday_price;
                    return [
                        'id' => $slot->id,
                        'name' => $slot->name,
                        'start_time' => $slot->start_time,
                        'end_time' => $slot->end_time,
                        'duration_hours' => $slot->duration_hours,
                        'price_per_night' => $basePrice,
                        'total_price' => $basePrice,
                        'nights' => 1,
                        'booking_type' => 'overnight'
                    ];
                })
                ->values()
                ->toArray();

            $allSlots = array_merge($dayUseSlots, $overnightSlots);

            // Calculate min price from all slots
            $prices = [];
            foreach ($allSlots as $slot) {
                $prices[] = $slot['price'] ?? ($slot['price_per_night'] ?? 0);
            }
            $minPrice = !empty($prices) ? min($prices) : null;

            $results[] = [
                'chalet' => $chalet,
                'slots' => $allSlots,
                'min_price' => $minPrice
            ];
        }

        // No sorting needed, just return all chalets
        return $results;
    }

    /**
     * Format chalet data for API response
     */
    private function formatChaletData(Chalet $chalet): array
    {
        return [
            'id' => $chalet->id,
            'name' => $chalet->name,
            'slug' => $chalet->slug,
            'description' => $chalet->description,
            'address' => $chalet->address,
            'city' => $chalet->city,
            'max_adults' => $chalet->max_adults,
            'max_children' => $chalet->max_children,
            'bedrooms_count' => $chalet->bedrooms_count,
            'bathrooms_count' => $chalet->bathrooms_count,
            'average_rating' => $chalet->average_rating,
            'total_reviews' => $chalet->total_reviews,
            'is_featured' => $chalet->is_featured,
            'base_price' => $chalet->base_price,
        ];
    }
}