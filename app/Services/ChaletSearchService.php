<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Chalet;
use Carbon\Carbon;

final class ChaletSearchService
{
    /**
     * Search chalets for single date (day-use slots only)
     * Returns all possible slot combinations including consecutive ones
     */
    public function searchForSingleDate(string $date, array $filters = []): array
    {
        $chalets = $this->getBaseQuery($filters)
            ->with(['timeSlots' => fn ($q) => $q->where('is_active', true)->where('is_overnight', false)])
            ->get();

        $results = [];

        foreach ($chalets as $chalet) {
            $availabilityService = new ChaletAvailabilityService($chalet);
            
            // Get all possible consecutive combinations
            $slotCombinations = $availabilityService->getConsecutiveSlotCombinations($date);
            
            $availableSlots = $chalet->timeSlots->filter(function ($slot) use ($availabilityService, $date) {
                return $availabilityService->isSingleSlotAvailable($date, $slot->id);
            });

            if ($availableSlots->isNotEmpty()) {
                $slotsData = $availableSlots->map(function ($slot) use ($availabilityService, $date) {
                    return [
                        'name' => $slot->name,
                        'start_time' => $slot->start_time,
                        'end_time' => $slot->end_time,
                        'duration_hours' => $slot->duration_hours,
                        'price' => $availabilityService->getPrice($date, $slot->id),
                    ];
                })->values()->toArray();

                if (!empty($slotsData)) {
                    $results[] = [
                        'chalet' => $this->formatChaletData($chalet),
                        'slots' => $slotsData,
                        'min_price' => min(array_column($slotsData, 'price')),
                    ];
                }
            }
        }

        // Sort by minimum price
        usort($results, fn($a, $b) => $a['min_price'] <=> $b['min_price']);

        return $results;
    }

    /**
     * Search chalets for date range (overnight slots only)
     */
    public function searchForDateRange(string $startDate, string $endDate, array $filters = []): array
    {
        // Validate date range
        if ($startDate === $endDate) {
            throw new \InvalidArgumentException('For date ranges, start and end dates must be different. Use searchForSingleDate for same-day bookings.');
        }

        $chalets = $this->getBaseQuery($filters)
            ->with(['timeSlots' => fn ($q) => $q->where('is_active', true)->where('is_overnight', true)])
            ->get();

        $results = [];

        foreach ($chalets as $chalet) {
            $availabilityService = new ChaletAvailabilityService($chalet);
            $availableSlots = $availabilityService->getAvailableOvernightSlots($startDate, $endDate);
            
            if ($availableSlots->isNotEmpty()) {
                $slotsArray = $availableSlots->map(function ($slot) {
                    return [
                        'name' => $slot['name'],
                        'start_time' => $slot['start_time'],
                        'end_time' => $slot['end_time'],
                        'duration_hours' => $slot['duration_hours'],
                        'price' => $slot['total_price'],
                    ];
                })->toArray();

                $results[] = [
                    'chalet' => $this->formatChaletData($chalet),
                    'slots' => $slotsArray,
                    'min_total_price' => min(array_column($slotsArray, 'price')),
                    'max_total_price' => max(array_column($slotsArray, 'price')),
                    'nights' => Carbon::parse($startDate)->diffInDays(Carbon::parse($endDate)),
                ];
            }
        }

        // Sort by minimum total price
        usort($results, fn($a, $b) => $a['min_total_price'] <=> $b['min_total_price']);

        return $results;
    }

    /**
     * Universal search method that automatically determines search type
     */
    public function search(string $startDate, string $endDate, array $filters = []): array
    {
        if ($startDate === $endDate) {
            return $this->searchForSingleDate($startDate, $filters);
        } else {
            return $this->searchForDateRange($startDate, $endDate, $filters);
        }
    }

    /**
     * Search with specific slot requirements
     */
    public function searchWithSlotRequirements(
        string $startDate, 
        string $endDate, 
        array $requiredSlotIds = [], 
        array $filters = []
    ): array {
        if ($startDate === $endDate) {
            return $this->searchForSingleDateWithSlots($startDate, $requiredSlotIds, $filters);
        } else {
            return $this->searchForDateRangeWithSlot($startDate, $endDate, $requiredSlotIds[0] ?? null, $filters);
        }
    }

    /**
     * Search for single date with specific slot requirements
     */
    private function searchForSingleDateWithSlots(string $date, array $slotIds, array $filters = []): array
    {
        $chalets = $this->getBaseQuery($filters)
            ->whereHas('timeSlots', function ($q) use ($slotIds) {
                $q->whereIn('id', $slotIds)->where('is_active', true)->where('is_overnight', false);
            })
            ->with('timeSlots')
            ->get();

        $results = [];

        foreach ($chalets as $chalet) {
            $availabilityService = new ChaletAvailabilityService($chalet);
            
            if ($availabilityService->isAvailableForDay($date, $slotIds)) {
                $totalPrice = $availabilityService->getTotalPriceForDay($date, $slotIds);
                $slots = $chalet->timeSlots->whereIn('id', $slotIds)->values();
                
                $results[] = [
                    'chalet' => $this->formatChaletData($chalet),
                    'selected_slots' => $slots->map(function ($slot) use ($date, $availabilityService) {
                        return [
                            'id' => $slot->id,
                            'name' => $slot->name,
                            'start_time' => $slot->start_time,
                            'end_time' => $slot->end_time,
                            'price' => $availabilityService->getPrice($date, $slot->id),
                        ];
                    })->toArray(),
                    'total_price' => $totalPrice,
                    'slots' => $chalet->timeSlots()->where('is_active', true)->get()->map(function($slot) use ($availabilityService, $date) {
                        return [
                            'name' => $slot->name,
                            'price' => $availabilityService->getPrice($date, $slot->id)
                        ];
                    })->all()
                ];
            }
        }

        usort($results, fn($a, $b) => $a['total_price'] <=> $b['total_price']);

        return $results;
    }

    /**
     * Search for date range with specific overnight slot
     */
    private function searchForDateRangeWithSlot(string $startDate, string $endDate, ?int $slotId, array $filters = []): array
    {
        if (!$slotId) {
            return [];
        }

        $chalets = $this->getBaseQuery($filters)
            ->whereHas('timeSlots', function ($q) use ($slotId) {
                $q->where('id', $slotId)->where('is_active', true)->where('is_overnight', true);
            })
            ->with('timeSlots')
            ->get();

        $results = [];

        foreach ($chalets as $chalet) {
            $availabilityService = new ChaletAvailabilityService($chalet);
            
            if ($availabilityService->isAvailableForOvernightRange($startDate, $endDate, $slotId)) {
                $totalPrice = $availabilityService->getTotalPriceForOvernightRange($startDate, $endDate, $slotId);
                $slot = $chalet->timeSlots->find($slotId);
                $nights = Carbon::parse($startDate)->diffInDays(Carbon::parse($endDate));
                
                $results[] = [
                    'chalet' => $this->formatChaletData($chalet),
                    'selected_slot' => [
                        'id' => $slot->id,
                        'name' => $slot->name,
                        'start_time' => $slot->start_time,
                        'end_time' => $slot->end_time,
                        'total_price' => $totalPrice,
                        'nights' => $nights,
                        'average_per_night' => $totalPrice / max(1, $nights),
                    ],
                ];
            }
        }

        usort($results, fn($a, $b) => $a['selected_slot']['total_price'] <=> $b['selected_slot']['total_price']);

        return $results;
    }

    /**
     * Get base query with common filters
     */
    private function getBaseQuery(array $filters = [])
    {
        return Chalet::query()
            ->where('status', 'active')
            ->when(isset($filters['city']), fn ($q) => $q->where('city', $filters['city']))
            ->when(isset($filters['max_adults']), fn ($q) => $q->where('max_adults', '>=', $filters['max_adults']))
            ->when(isset($filters['max_children']), fn ($q) => $q->where('max_children', '>=', $filters['max_children']))
            ->when(isset($filters['min_bedrooms']), fn ($q) => $q->where('bedrooms_count', '>=', $filters['min_bedrooms']))
            ->when(isset($filters['min_bathrooms']), fn ($q) => $q->where('bathrooms_count', '>=', $filters['min_bathrooms']))
            ->when(isset($filters['amenities']), function ($q) use ($filters) {
                $q->whereHas('amenities', function ($subQuery) use ($filters) {
                    $subQuery->whereIn('amenities.id', $filters['amenities']);
                }, '=', count($filters['amenities']));
            })
            ->when(isset($filters['facilities']), function ($q) use ($filters) {
                $q->whereHas('facilities', function ($subQuery) use ($filters) {
                    $subQuery->whereIn('facilities.id', $filters['facilities']);
                }, '=', count($filters['facilities']));
            })
            ->when(isset($filters['max_price']), function ($q) use ($filters) {
                // This is complex as price depends on time slots and dates
                // Consider implementing a separate price filtering method
            });
    }

    /**
     * Format chalet data for response
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
            'latitude' => $chalet->latitude,
            'longitude' => $chalet->longitude,
            'max_adults' => $chalet->max_adults,
            'max_children' => $chalet->max_children,
            'bedrooms_count' => $chalet->bedrooms_count,
            'bathrooms_count' => $chalet->bathrooms_count,
            'average_rating' => $chalet->average_rating,
            'total_reviews' => $chalet->total_reviews,
            'is_featured' => $chalet->is_featured,
            // Add any other fields you want to expose
        ];
    }
}