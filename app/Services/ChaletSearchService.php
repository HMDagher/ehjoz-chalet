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
            
            if ($bookingType === 'day-use') {
                // Get all day-use slots and mark their availability
                $dayUseSlots = $chalet->timeSlots
                    ->where('is_overnight', false)
                    ->map(function($slot) use ($availabilityChecker, $startDate) {
                        $price = $availabilityChecker->calculateDayUsePrice($startDate, $slot->id);
                        return [
                            'id' => $slot->id,
                            'name' => $slot->name,
                            'start_time' => $slot->start_time,
                            'end_time' => $slot->end_time,
                            'duration_hours' => $slot->duration_hours,
                            'price' => $price,
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
                
                $results[] = [
                    'chalet' => $this->formatChaletData($chalet),
                    'slots' => $allSlots,
                    'min_price' => $minPrice,
                    'booking_type' => 'day-use'
                ];
            } else {
                // Get all overnight slots and mark their availability
                $overnightSlots = $chalet->timeSlots
                    ->where('is_overnight', true)
                    ->map(function($slot) use ($availabilityChecker, $startDate, $endDate) {
                        $priceData = $availabilityChecker->calculateOvernightPrice($startDate, $endDate, $slot->id);
                        $nights = $priceData['nights'] ?? 1;
                        $originalTotal = $priceData['original_price'] ?? $priceData['total_price'];
                        $originalPerNight = $nights > 0 ? $originalTotal / $nights : $originalTotal;
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
        $today = Carbon::today()->format('Y-m-d');
        $tomorrow = Carbon::tomorrow()->format('Y-m-d');
        
        $chalets = Chalet::where('status', 'active')
            ->with(['timeSlots' => function($query) {
                $query->where('is_active', true);
            }])
            ->get();

        $results = [];

        foreach ($chalets as $chalet) {
            $availabilityChecker = new \App\Services\ChaletAvailabilityChecker($chalet);
            $allSlots = [];
            
            // Get all day-use slots with availability status
            $dayUseSlots = $chalet->timeSlots
                ->where('is_active', true)
                ->where('is_overnight', false)
                ->map(function($slot) use ($availabilityChecker, $today) {
                    $price = $availabilityChecker->calculateDayUsePrice($today, $slot->id);
                    return [
                        'id' => $slot->id,
                        'name' => $slot->name,
                        'start_time' => $slot->start_time,
                        'end_time' => $slot->end_time,
                        'duration_hours' => $slot->duration_hours,
                        'price' => $price,
                        'booking_type' => 'day-use'
                    ];
                })
                ->values()
                ->toArray();
                
            // Sort day-use slots by availability
            usort($dayUseSlots, function($a, $b) {
                return $b['price'] <=> $a['price'];
            });
                
            // Get all overnight slots with availability status
            $overnightSlots = $chalet->timeSlots
                ->where('is_active', true)
                ->where('is_overnight', true)
                ->map(function($slot) use ($availabilityChecker, $today, $tomorrow) {
                    $priceData = $availabilityChecker->calculateOvernightPrice($today, $tomorrow, $slot->id);
                    $nights = $priceData['nights'] ?? 1;
                    $originalTotal = $priceData['original_price'] ?? $priceData['total_price'];
                    $originalPerNight = $nights > 0 ? $originalTotal / $nights : $originalTotal;
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
                
            // Sort overnight slots by availability
            usort($overnightSlots, function($a, $b) {
                return $b['price_per_night'] <=> $a['price_per_night'];
            });
                
            $allSlots = array_merge($dayUseSlots, $overnightSlots);
            
            // Calculate min price from available slots only
            $availableSlots = array_filter($allSlots, function($slot) {
                return (array_key_exists('price', $slot) && $slot['price'] !== null)
                    || (array_key_exists('price_per_night', $slot) && $slot['price_per_night'] !== null);
            });
            $prices = [];
            foreach ($availableSlots as $slot) {
                $prices[] = $slot['price'] ?? ($slot['price_per_night'] ?? 0);
            }
            $minPrice = !empty($prices) ? min($prices) : null;
            
            $results[] = [
                'chalet' => $chalet,
                'slots' => $allSlots,
                'min_price' => $minPrice
            ];
        }

        // Sort by min_price, with chalets that have available slots first
        if (!empty($results)) {
            usort($results, function($a, $b) {
                // If either doesn't have a min_price (no available slots), put it at the end
                if ($a['min_price'] === null && $b['min_price'] === null) return 0;
                if ($a['min_price'] === null) return 1;
                if ($b['min_price'] === null) return -1;
                
                return $a['min_price'] <=> $b['min_price'];
            });
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
            'base_price' => $chalet->base_price,
        ];
    }
}