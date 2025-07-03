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
        
        // Get active chalets with appropriate time slots
        $chalets = Chalet::where('status', 'active')
            ->with(['timeSlots' => function($query) use ($bookingType) {
                $query->where('is_active', true)
                      ->where('is_overnight', $bookingType === 'overnight');
            }])
            ->get();
            
        \Log::info('SearchService: chalets loaded', [
            'count' => $chalets->count(),
            'ids' => $chalets->pluck('id')->toArray(),
        ]);

        $results = [];

        foreach ($chalets as $chalet) {
            // Skip chalets with no active time slots of the requested type
            if ($chalet->timeSlots->isEmpty()) {
                \Log::info('SearchService: chalet has no active time slots', ['chalet_id' => $chalet->id]);
                continue;
            }
            
            $availabilityChecker = new ChaletAvailabilityChecker($chalet);
            $availableSlots = [];
            
            if ($bookingType === 'day-use') {
                // For day-use, get available slots for the specific date
                $slotData = $availabilityChecker->getAvailableDayUseSlots($startDate);
                
                \Log::info('SearchService: day-use slotData', [
                    'chalet_id' => $chalet->id,
                    'slot_count' => $slotData->count(),
                    'slots' => $slotData->toArray(),
                ]);

                if ($slotData->isNotEmpty()) {
                    $availableSlots = $slotData->toArray();
                    $minPrice = min(array_column($availableSlots, 'price'));
                    $combinations = $availabilityChecker->findConsecutiveSlotCombinations($startDate);
                    
                    $results[] = [
                        'chalet' => $this->formatChaletData($chalet),
                        'slots' => $availableSlots,
                        'min_price' => $minPrice,
                        'combinations' => $combinations,
                        'booking_type' => 'day-use'
                    ];
                } else {
                    \Log::info('SearchService: no available day-use slots', ['chalet_id' => $chalet->id]);
                }
            } else {
                // For overnight bookings, check availability for the date range
                $slotData = $availabilityChecker->getAvailableOvernightSlots($startDate, $endDate);
                
                \Log::info('SearchService: overnight slotData', [
                    'chalet_id' => $chalet->id,
                    'slot_count' => $slotData->count(),
                    'slots' => $slotData->toArray(),
                ]);

                if ($slotData->isNotEmpty()) {
                    $availableSlots = $slotData->toArray();
                    $minPerNightPrice = min(array_column($availableSlots, 'price_per_night'));
                    $nights = Carbon::parse($startDate)->diffInDays(Carbon::parse($endDate));
                    $nights = max(1, $nights);
                    
                    $results[] = [
                        'chalet' => $this->formatChaletData($chalet),
                        'slots' => $availableSlots,
                        'min_price' => $minPerNightPrice, // Per night price for listing page
                        'min_total_price' => $minPerNightPrice * $nights,
                        'nights' => $nights,
                        'booking_type' => 'overnight'
                    ];
                } else {
                    \Log::info('SearchService: no available overnight slots', ['chalet_id' => $chalet->id]);
                }
            }
        }

        // Sort by min_price (ascending)
        if (!empty($results)) {
        usort($results, fn($a, $b) => $a['min_price'] <=> $b['min_price']);
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
        $today = Carbon::today()->format('Y-m-d');
        $tomorrow = Carbon::tomorrow()->format('Y-m-d');
        
        $chalets = Chalet::where('status', 'active')
            ->with(['timeSlots' => function($query) {
                $query->where('is_active', true);
            }])
            ->get();

        $results = [];

        foreach ($chalets as $chalet) {
            $availabilityChecker = new ChaletAvailabilityChecker($chalet);
            $allSlots = [];
            
            // Get day-use slots
            $dayUseSlots = $chalet->timeSlots
                ->where('is_active', true)
                ->where('is_overnight', false)
                ->filter(function($slot) use ($availabilityChecker, $today) {
                    return $availabilityChecker->isDayUseSlotAvailable($today, $slot->id);
                })
                ->map(function($slot) use ($availabilityChecker, $today) {
                        return [
                            'id' => $slot->id,
                            'name' => $slot->name,
                            'start_time' => $slot->start_time,
                            'end_time' => $slot->end_time,
                        'duration_hours' => $slot->duration_hours,
                        'price' => $availabilityChecker->calculateDayUsePrice($today, $slot->id),
                        'booking_type' => 'day-use'
                    ];
                })
                ->values()
                ->toArray();
                
            // Get overnight slots
            $overnightSlots = $chalet->timeSlots
                ->where('is_active', true)
                ->where('is_overnight', true)
                ->filter(function($slot) use ($availabilityChecker, $today, $tomorrow) {
                    return $availabilityChecker->isOvernightSlotAvailable($today, $tomorrow, $slot->id);
                })
                ->map(function($slot) use ($availabilityChecker, $today, $tomorrow) {
                    $priceData = $availabilityChecker->calculateOvernightPrice($today, $tomorrow, $slot->id);
                    return [
                        'id' => $slot->id,
                        'name' => $slot->name,
                        'start_time' => $slot->start_time,
                        'end_time' => $slot->end_time,
                        'duration_hours' => $slot->duration_hours,
                        'price_per_night' => $priceData['price_per_night'],
                        'booking_type' => 'overnight'
                    ];
                })
                ->values()
                ->toArray();
                
            $allSlots = array_merge($dayUseSlots, $overnightSlots);
            
            // Always add the chalet, even if $allSlots is empty
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

        // Sort by min_price
        if (!empty($results)) {
            usort($results, fn($a, $b) => $a['min_price'] <=> $b['min_price']);
        }

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
        ];
    }
}