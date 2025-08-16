<?php

namespace App\Services;

use App\Models\Chalet;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ChaletSearchService
{
    private AvailabilityService $availabilityService;

    private const SEARCH_CACHE_TTL = 1800; // 30 minutes for search results

    public function __construct(AvailabilityService $availabilityService)
    {
        $this->availabilityService = $availabilityService;
    }

    /**
     * Search for available chalets
     */
    public function searchAvailableChalets(array $searchParams): array
    {
        try {
            // Validate and normalize search parameters
            $validatedParams = $this->validateSearchParams($searchParams);
            if (! $validatedParams['valid']) {
                return [
                    'success' => false,
                    'errors' => $validatedParams['errors'],
                    'chalets' => [],
                    'total_count' => 0,
                ];
            }

            $params = $validatedParams['params'];

            // Generate cache key for search
            $cacheKey = $this->generateSearchCacheKey($params);

            // Try to get from cache
            if ($cached = Cache::get($cacheKey)) {
                Log::info('Search results served from cache', ['cache_key' => $cacheKey]);

                return $cached;
            }

            // Perform the search
            $searchResults = $this->performSearch($params);

            // Cache the results
            Cache::put($cacheKey, $searchResults, self::SEARCH_CACHE_TTL);

            return $searchResults;

        } catch (\Exception $e) {
            Log::error('Chalet search failed', [
                'params' => $searchParams,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'errors' => ['Search system temporarily unavailable'],
                'chalets' => [],
                'total_count' => 0,
            ];
        }
    }

    /**
     * Perform the actual search
     */
    private function performSearch(array $params): array
    {
        // Get base chalets query
        $chalets = $this->getBaseChaletsQuery($params);

        // Get total count before filtering by availability
        $totalPotentialChalets = $chalets->count();

        $availableChalets = [];
        $processedCount = 0;

        foreach ($chalets->get() as $chalet) {
            $processedCount++;

            // Check availability for this chalet
            $availability = $this->availabilityService->checkAvailability(
                $chalet->id,
                $params['start_date'],
                $params['end_date'],
                $params['booking_type']
            );

            if ($availability['available']) {
                $availableChalets[] = $this->formatChaletResult($chalet, $availability, $params);
            }

            // Log progress for long searches
            if ($processedCount % 10 === 0) {
                Log::debug('Search progress', [
                    'processed' => $processedCount,
                    'total' => $totalPotentialChalets,
                    'found_available' => count($availableChalets),
                ]);
            }
        }

        // Sort results by relevance/price
        $sortedResults = $this->sortSearchResults($availableChalets, $params);

        return [
            'success' => true,
            'chalets' => $sortedResults,
            'total_count' => count($sortedResults),
            'search_metadata' => [
                'searched_chalets' => $totalPotentialChalets,
                'available_chalets' => count($availableChalets),
                'booking_type' => $params['booking_type'],
                'date_range' => [
                    'start_date' => $params['start_date'],
                    'end_date' => $params['end_date'],
                ],
                'search_timestamp' => Carbon::now()->toISOString(),
            ],
        ];
    }

    /**
     * Get base chalets query with basic filters
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    private function getBaseChaletsQuery(array $params)
    {
        $query = Chalet::where('status', \App\Enums\ChaletStatus::Active)
            ->whereHas('timeSlots', function ($q) use ($params) {
                $q->where('is_active', true);

                // Filter by booking type
                if ($params['booking_type'] === 'day-use') {
                    $q->where('is_overnight', false);
                } elseif ($params['booking_type'] === 'overnight') {
                    $q->where('is_overnight', true);
                }

                // Filter by date availability (available_days)
                $this->addDateAvailabilityFilter($q, $params);
            });

        // Add additional filters if needed (location, price range, etc.)
        // This can be extended later

        return $query->with(['timeSlots' => function ($q) use ($params) {
            $q->where('is_active', true);
            if ($params['booking_type'] === 'day-use') {
                $q->where('is_overnight', false);
            } elseif ($params['booking_type'] === 'overnight') {
                $q->where('is_overnight', true);
            }
        }]);
    }

    /**
     * Add date availability filter to time slots query
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     */
    private function addDateAvailabilityFilter($query, array $params): void
    {
        $startDate = Carbon::createFromFormat('Y-m-d', $params['start_date']);
        $endDate = $params['end_date']
            ? Carbon::createFromFormat('Y-m-d', $params['end_date'])
            : $startDate;

        // Get all days of week in the date range
        $daysInRange = [];
        $current = $startDate->copy();

        while ($current->lte($endDate)) {
            $dayName = strtolower($current->format('l')); // 'monday', 'tuesday', etc.
            if (! in_array($dayName, $daysInRange)) {
                $daysInRange[] = $dayName;
            }
            $current->addDay();
        }

        // Filter slots that are available on at least one day in the range
        $query->where(function ($q) use ($daysInRange) {
            foreach ($daysInRange as $day) {
                $q->orWhereJsonContains('available_days', $day);
            }
        });
    }

    /**
     * Format chalet result for API response
     */
    private function formatChaletResult(Chalet $chalet, array $availability, array $params): array
    {
        // Calculate pricing summary
        $pricingSummary = $this->calculatePricingSummary($availability, $params);

        return [
            'chalet_id' => $chalet->id,
            'name' => $chalet->name,
            'slug' => $chalet->slug,
            'location' => [
                'address' => $chalet->address ?? null,
                'city' => $chalet->city ?? null,
                'latitude' => $chalet->latitude,
                'longitude' => $chalet->longitude,
            ],
            'images' => $chalet->getMedia('gallery')->map(function ($media) {
                return [
                    'url' => $media->getUrl(),
                    'thumb' => $media->getUrl('thumb'),
                    'preview' => $media->getUrl('preview'),
                ];
            })->toArray(),
            'description' => $chalet->description ?? null,
            'capacity' => [
                'max_adults' => $chalet->max_adults,
                'max_children' => $chalet->max_children,
                'total_capacity' => ($chalet->max_adults ?? 0) + ($chalet->max_children ?? 0),
            ],
            'amenities' => $chalet->amenities->pluck('name')->toArray() ?? [],
            'availability' => [
                'available_slots' => $availability['available_slots'],
                'consecutive_combinations' => $availability['consecutive_combinations'] ?? [],
                'booking_type' => $params['booking_type'],
            ],
            'pricing' => $pricingSummary,
            'rating' => [
                'average' => $chalet->average_rating ?? 0,
                'reviews_count' => $chalet->total_reviews ?? 0,
            ],
        ];
    }

    /**
     * Calculate pricing summary for search results
     */
    private function calculatePricingSummary(array $availability, array $params): array
    {
        if (empty($availability['available_slots'])) {
            return [
                'min_price' => 0,
                'max_price' => 0,
                'currency' => 'USD', // Configure this
                'price_breakdown' => [],
            ];
        }

        $allPrices = [];
        $priceBreakdown = [];

        foreach ($availability['available_slots'] as $slot) {
            foreach ($slot['pricing_info'] as $date => $pricing) {
                $allPrices[] = $pricing['final_price'];

                $priceBreakdown[] = [
                    'slot_id' => $slot['slot_id'],
                    'date' => $date,
                    'time' => $slot['start_time'].' - '.$slot['end_time'],
                    'base_price' => $pricing['base_price'],
                    'adjustment' => $pricing['adjustment'],
                    'final_price' => $pricing['final_price'],
                    'is_weekend' => $pricing['is_weekend'],
                ];
            }
        }

        return [
            'min_price' => ! empty($allPrices) ? min($allPrices) : 0,
            'max_price' => ! empty($allPrices) ? max($allPrices) : 0,
            'currency' => 'USD', // Make this configurable
            'total_slots' => count($availability['available_slots']),
            'price_breakdown' => $priceBreakdown,
        ];
    }

    /**
     * Sort search results
     */
    private function sortSearchResults(array $chalets, array $params): array
    {
        // Default sort by minimum price ascending
        usort($chalets, function ($a, $b) {
            return $a['pricing']['min_price'] <=> $b['pricing']['min_price'];
        });

        return $chalets;
    }

    /**
     * Validate search parameters
     */
    private function validateSearchParams(array $params): array
    {
        $errors = [];
        $normalized = [];

        // Validate booking_type
        if (empty($params['booking_type']) || ! in_array($params['booking_type'], ['day-use', 'overnight'])) {
            $errors[] = 'booking_type is required and must be either "day-use" or "overnight"';
        } else {
            $normalized['booking_type'] = $params['booking_type'];
        }

        // Validate start_date
        if (empty($params['start_date'])) {
            $errors[] = 'start_date is required';
        } else {
            try {
                $startDate = Carbon::createFromFormat('Y-m-d', $params['start_date']);
                if ($startDate->isPast()) {
                    $errors[] = 'start_date cannot be in the past';
                } else {
                    $normalized['start_date'] = $params['start_date'];
                }
            } catch (\Exception $e) {
                $errors[] = 'start_date must be in format Y-m-d (e.g., 2025-08-20)';
            }
        }

        // Validate end_date for overnight bookings
        if ($normalized['booking_type'] === 'overnight') {
            if (empty($params['end_date'])) {
                $errors[] = 'end_date is required for overnight bookings';
            } else {
                try {
                    $endDate = Carbon::createFromFormat('Y-m-d', $params['end_date']);
                    if (isset($normalized['start_date']) && $endDate->lt(Carbon::createFromFormat('Y-m-d', $normalized['start_date']))) {
                        $errors[] = 'end_date must be after or equal to start_date';
                    } else {
                        $normalized['end_date'] = $params['end_date'];
                    }
                } catch (\Exception $e) {
                    $errors[] = 'end_date must be in format Y-m-d (e.g., 2025-08-22)';
                }
            }
        } else {
            // For day-use, end_date should be null or same as start_date
            $normalized['end_date'] = $params['end_date'] ?? null;
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'params' => $normalized,
        ];
    }

    /**
     * Generate cache key for search
     */
    private function generateSearchCacheKey(array $params): string
    {
        $keyParts = [
            'chalet_search',
            $params['booking_type'],
            $params['start_date'],
            $params['end_date'] ?? 'null',
        ];

        return implode('_', $keyParts);
    }

    /**
     * Clear search cache
     * Should be called when chalets, time slots, or availability changes
     */
    public static function clearSearchCache(?int $chaletId = null): void
    {
        try {
            if ($chaletId) {
                // For specific chalet changes, we'd need more sophisticated cache invalidation
                // For now, clear all search cache patterns
                $keys = Cache::getRedis()->keys('chalet_search_*');
                if (! empty($keys)) {
                    Cache::getRedis()->del($keys);
                }
            } else {
                // Clear all search cache patterns
                $keys = Cache::getRedis()->keys('chalet_search_*');
                if (! empty($keys)) {
                    Cache::getRedis()->del($keys);
                }
            }

            Log::info('Search cache cleared', ['chalet_id' => $chaletId]);
        } catch (\Exception $e) {
            Log::error('Failed to clear search cache', [
                'chalet_id' => $chaletId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get search suggestions based on current availability
     */
    public function getSearchSuggestions(array $searchParams): array
    {
        // This can suggest alternative dates, different booking types, etc.
        // Implement based on your business needs

        return [
            'alternative_dates' => [],
            'alternative_booking_types' => [],
            'nearby_available_chalets' => [],
        ];
    }
}
