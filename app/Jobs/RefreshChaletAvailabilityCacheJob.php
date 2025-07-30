<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Chalet;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Carbon\Carbon;

class RefreshChaletAvailabilityCacheJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public ?int $chaletId;
    public int $tries = 3;
    public int $timeout = 300; // 5 minutes timeout

    /**
     * Create a new job instance.
     * 
     * @param int|null $chaletId If null, refresh cache for all active chalets
     */
    public function __construct(?int $chaletId = null)
    {
        $this->chaletId = $chaletId;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $startTime = microtime(true);
        
        if ($this->chaletId) {
            // Refresh cache for specific chalet
            $this->refreshSingleChalet($this->chaletId);
        } else {
            // Refresh cache for all active chalets
            $this->refreshAllChalets();
        }
        
        $executionTime = round((microtime(true) - $startTime) * 1000, 2);
        
        \Log::info('Periodic cache refresh completed', [
            'chalet_id' => $this->chaletId ?? 'all',
            'execution_time_ms' => $executionTime,
            'timestamp' => now()->toISOString()
        ]);
    }

    /**
     * Refresh cache for a single chalet
     */
    private function refreshSingleChalet(int $chaletId): void
    {
        $chalet = Chalet::where('id', $chaletId)->where('status', 'active')->first();
        
        if (!$chalet) {
            \Log::warning('Chalet not found or inactive for periodic cache refresh', [
                'chalet_id' => $chaletId
            ]);
            return;
        }

        \Log::info('Starting periodic cache refresh for chalet', [
            'chalet_id' => $chaletId,
            'chalet_name' => $chalet->name,
            'chalet_slug' => $chalet->slug
        ]);

        $result = $this->refreshChaletCache($chalet);
        
        \Log::info('Completed periodic cache refresh for chalet', [
            'chalet_id' => $chaletId,
            'result' => $result
        ]);
    }

    /**
     * Refresh cache for all active chalets
     */
    private function refreshAllChalets(): void
    {
        $chalets = Chalet::where('status', 'active')->get();
        
        \Log::info('Starting periodic cache refresh for all chalets', [
            'total_chalets' => $chalets->count()
        ]);

        $totalWarmed = 0;
        $totalFailed = 0;
        $results = [];

        foreach ($chalets as $chalet) {
            try {
                $result = $this->refreshChaletCache($chalet);
                $totalWarmed += $result['warmed_count'];
                $totalFailed += $result['failed_count'];
                $results[] = $result;
                
                // Small delay between chalets to avoid overwhelming the system
                usleep(100000); // 0.1 second
                
            } catch (\Exception $e) {
                $totalFailed++;
                \Log::error('Failed to refresh cache for chalet during periodic refresh', [
                    'chalet_id' => $chalet->id,
                    'chalet_name' => $chalet->name,
                    'error' => $e->getMessage()
                ]);
            }
        }

        \Log::info('Completed periodic cache refresh for all chalets', [
            'total_chalets' => $chalets->count(),
            'total_warmed' => $totalWarmed,
            'total_failed' => $totalFailed
        ]);
    }

    /**
     * Refresh cache for a specific chalet with common date ranges
     */
    private function refreshChaletCache(Chalet $chalet): array
    {
        $bookingTypes = ['day-use', 'overnight'];
        $dateRanges = $this->getCommonDateRanges();
        
        $warmedCount = 0;
        $failedCount = 0;
        $results = [];
        
        foreach ($bookingTypes as $bookingType) {
            foreach ($dateRanges as $range) {
                [$startDate, $endDate, $rangeName] = $range;
                $cacheKey = "chalet_unavailable_dates_{$chalet->id}_{$bookingType}_{$startDate}_{$endDate}";
                
                try {
                    // Generate fresh data and update cache
                    $data = $this->generateFreshAvailabilityData($chalet, $bookingType, $startDate, $endDate);
                    \Illuminate\Support\Facades\Cache::put($cacheKey, $data, 1800); // 30 minutes
                    
                    $warmedCount++;
                    $results[] = [
                        'cache_key' => $cacheKey,
                        'booking_type' => $bookingType,
                        'range' => $rangeName,
                        'status' => 'refreshed'
                    ];
                    
                } catch (\Exception $e) {
                    $failedCount++;
                    $results[] = [
                        'cache_key' => $cacheKey,
                        'booking_type' => $bookingType,
                        'range' => $rangeName,
                        'status' => 'failed',
                        'error' => $e->getMessage()
                    ];
                    
                    \Log::error('Failed to refresh cache entry', [
                        'chalet_id' => $chalet->id,
                        'booking_type' => $bookingType,
                        'cache_key' => $cacheKey,
                        'error' => $e->getMessage()
                    ]);
                }
            }
        }
        
        return [
            'chalet_id' => $chalet->id,
            'chalet_name' => $chalet->name,
            'warmed_count' => $warmedCount,
            'failed_count' => $failedCount,
            'results' => $results
        ];
    }

    /**
     * Generate fresh availability data
     */
    private function generateFreshAvailabilityData(Chalet $chalet, string $bookingType, string $startDate, string $endDate): array
    {
        $availabilityChecker = new \App\Services\ChaletAvailabilityChecker($chalet);
        
        $unavailableDayUseDates = [];
        $unavailableOvernightDates = [];
        $fullyBlockedDates = [];
        
        $currentDate = Carbon::parse($startDate);
        $endDateCarbon = Carbon::parse($endDate);
        
        while ($currentDate <= $endDateCarbon) {
            $dateStr = $currentDate->format('Y-m-d');
            
            // Check day-use availability
            $availableDayUseSlots = $availabilityChecker->getAvailableDayUseSlots($dateStr);
            if ($availableDayUseSlots->isEmpty()) {
                $unavailableDayUseDates[] = $dateStr;
            }
            
            // Check overnight availability
            $nextDay = $currentDate->copy()->addDay()->format('Y-m-d');
            $availableOvernightSlots = $availabilityChecker->getAvailableOvernightSlots($dateStr, $nextDay);
            if ($availableOvernightSlots->isEmpty()) {
                $unavailableOvernightDates[] = $dateStr;
            }
            
            // Check if entire day is blocked
            if ($availableDayUseSlots->isEmpty() && $availableOvernightSlots->isEmpty()) {
                $fullyBlockedDates[] = $dateStr;
            }
            
            $currentDate->addDay();
        }

        return [
            'unavailable_day_use_dates' => $unavailableDayUseDates,
            'unavailable_overnight_dates' => $unavailableOvernightDates,
            'fully_blocked_dates' => $fullyBlockedDates,
            'date_range' => [
                'start' => $startDate,
                'end' => $endDate
            ],
            'generated_at' => now()->toISOString(),
            'refreshed_at' => now()->toISOString(),
            'periodic_refresh' => true
        ];
    }

    /**
     * Get common date ranges that are likely to be requested
     */
    private function getCommonDateRanges(): array
    {
        $now = now();
        
        return [
            // 3 months from today (most common - matches the frontend default)
            [$now->format('Y-m-d'), $now->copy()->addMonths(3)->format('Y-m-d'), '3_months'],
            
            // 1 month from today
            [$now->format('Y-m-d'), $now->copy()->addMonths(1)->format('Y-m-d'), '1_month'],
            
            // 90 days from today (matches the frontend example)
            [$now->format('Y-m-d'), $now->copy()->addDays(90)->format('Y-m-d'), '90_days'],
            
            // Current month
            [$now->startOfMonth()->format('Y-m-d'), $now->copy()->endOfMonth()->format('Y-m-d'), 'current_month'],
            
            // Next month
            [$now->copy()->addMonth()->startOfMonth()->format('Y-m-d'), $now->copy()->addMonth()->endOfMonth()->format('Y-m-d'), 'next_month'],
        ];
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        \Log::error('Periodic cache refresh job failed', [
            'chalet_id' => $this->chaletId ?? 'all',
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString()
        ]);
    }
}