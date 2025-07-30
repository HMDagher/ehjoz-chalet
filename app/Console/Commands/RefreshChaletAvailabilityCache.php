<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\RefreshChaletAvailabilityCacheJob;
use App\Models\Chalet;
use Illuminate\Console\Command;

class RefreshChaletAvailabilityCache extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cache:refresh-chalet-availability {chalet_id?} {--async} {--all}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Refresh chalet availability cache for a specific chalet or all chalets';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $chaletId = $this->argument('chalet_id');
        $async = $this->option('async');
        $all = $this->option('all');
        
        if ($all && $chaletId) {
            $this->error('Cannot specify both --all and chalet_id');
            return 1;
        }
        
        if ($all) {
            return $this->refreshAllChalets($async);
        }
        
        if ($chaletId) {
            return $this->refreshSingleChalet((int) $chaletId, $async);
        }
        
        // Default: refresh all chalets
        return $this->refreshAllChalets($async);
    }
    
    /**
     * Refresh cache for a single chalet
     */
    private function refreshSingleChalet(int $chaletId, bool $async): int
    {
        $chalet = Chalet::where('id', $chaletId)->where('status', 'active')->first();
        
        if (!$chalet) {
            $this->error("Chalet with ID {$chaletId} not found or not active");
            return 1;
        }
        
        $this->info("Refreshing availability cache for chalet: {$chalet->name} (ID: {$chaletId})");
        
        if ($async) {
            RefreshChaletAvailabilityCacheJob::dispatch($chaletId);
            $this->info('Cache refresh job dispatched to queue');
        } else {
            $startTime = microtime(true);
            RefreshChaletAvailabilityCacheJob::dispatchSync($chaletId);
            $executionTime = round((microtime(true) - $startTime) * 1000, 2);
            $this->info("Cache refresh completed in {$executionTime}ms");
        }
        
        return 0;
    }
    
    /**
     * Refresh cache for all active chalets
     */
    private function refreshAllChalets(bool $async): int
    {
        $chalets = Chalet::where('status', 'active')->get();
        
        if ($chalets->isEmpty()) {
            $this->warn('No active chalets found');
            return 0;
        }
        
        $this->info("Refreshing availability cache for {$chalets->count()} active chalets");
        
        if ($async) {
            RefreshChaletAvailabilityCacheJob::dispatch();
            $this->info('Cache refresh job dispatched to queue');
        } else {
            $startTime = microtime(true);
            
            $progressBar = $this->output->createProgressBar($chalets->count());
            $progressBar->start();
            
            foreach ($chalets as $chalet) {
                RefreshChaletAvailabilityCacheJob::dispatchSync($chalet->id);
                $progressBar->advance();
            }
            
            $progressBar->finish();
            $this->newLine();
            
            $executionTime = round((microtime(true) - $startTime) * 1000, 2);
            $this->info("Cache refresh completed for all chalets in {$executionTime}ms");
        }
        
        return 0;
    }
}