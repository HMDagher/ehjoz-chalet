<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class ClearChaletAvailabilityCache extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cache:clear-chalet-availability {chalet_id? : The chalet ID to clear cache for (optional)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clear chalet availability cache for a specific chalet or all chalets';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $chaletId = $this->argument('chalet_id');
        
        if ($chaletId) {
            $this->clearChaletCache((int) $chaletId);
            $this->info("Cleared availability cache for chalet ID: {$chaletId}");
        } else {
            // Clear all chalet availability caches
            $this->clearAllChaletCaches();
            $this->info('Cleared availability cache for all chalets');
        }
        
        return 0;
    }
    
    /**
     * Clear cache for a specific chalet
     */
    private function clearChaletCache(int $chaletId): void
    {
        $bookingTypes = ['day-use', 'overnight'];
        $dateRanges = [
            [now()->format('Y-m-d'), now()->addMonths(3)->format('Y-m-d')],
            [now()->format('Y-m-d'), now()->addMonths(1)->format('Y-m-d')],
            [now()->format('Y-m-d'), now()->addDays(90)->format('Y-m-d')],
        ];
        
        $clearedCount = 0;
        foreach ($bookingTypes as $bookingType) {
            foreach ($dateRanges as [$startDate, $endDate]) {
                $cacheKey = "chalet_unavailable_dates_{$chaletId}_{$bookingType}_{$startDate}_{$endDate}";
                if (Cache::has($cacheKey)) {
                    Cache::forget($cacheKey);
                    $clearedCount++;
                }
            }
        }
        
        $this->line("Cleared {$clearedCount} cache entries for chalet {$chaletId}");
    }
    
    /**
     * Clear cache for all chalets (this is a more aggressive approach)
     */
    private function clearAllChaletCaches(): void
    {
        // Get all chalet IDs from database
        $chaletIds = \App\Models\Chalet::pluck('id');
        
        $totalCleared = 0;
        foreach ($chaletIds as $chaletId) {
            $bookingTypes = ['day-use', 'overnight'];
            $dateRanges = [
                [now()->format('Y-m-d'), now()->addMonths(3)->format('Y-m-d')],
                [now()->format('Y-m-d'), now()->addMonths(1)->format('Y-m-d')],
                [now()->format('Y-m-d'), now()->addDays(90)->format('Y-m-d')],
            ];
            
            foreach ($bookingTypes as $bookingType) {
                foreach ($dateRanges as [$startDate, $endDate]) {
                    $cacheKey = "chalet_unavailable_dates_{$chaletId}_{$bookingType}_{$startDate}_{$endDate}";
                    if (Cache::has($cacheKey)) {
                        Cache::forget($cacheKey);
                        $totalCleared++;
                    }
                }
            }
        }
        
        $this->line("Cleared {$totalCleared} cache entries for {$chaletIds->count()} chalets");
    }
}