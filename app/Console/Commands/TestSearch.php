<?php

namespace App\Console\Commands;

use App\Services\ChaletSearchService;
use Illuminate\Console\Command;
use Carbon\Carbon;

class TestSearch extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:search {start_date} {end_date?} {--type=day-use}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test the chalet search functionality with given dates and booking type.';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(ChaletSearchService $searchService)
    {
        $startDate = $this->argument('start_date');
        $endDate = $this->argument('end_date');
        $bookingType = $this->option('type');

        try {
            $startDate = Carbon::createFromFormat('Y-m-d', $startDate)->format('Y-m-d');
            if ($endDate) {
                $endDate = Carbon::createFromFormat('Y-m-d', $endDate)->format('Y-m-d');
            }
        } catch (\Exception $e) {
            $this->error('Invalid date format. Please use YYYY-MM-DD.');
            return 1;
        }

        if (!in_array($bookingType, ['day-use', 'overnight'])) {
            $this->error('Invalid booking type. Use \'day-use\' or \'overnight\'.');
            return 1;
        }

        $this->info("Searching for chalets...");
        $this->line("Start Date: {$startDate}");
        if ($endDate) {
            $this->line("End Date:   {$endDate}");
        }
        $this->line("Booking Type: {$bookingType}");

        $searchParams = [
            'start_date' => $startDate,
            'end_date' => $endDate,
            'booking_type' => $bookingType,
        ];

        $results = $searchService->searchAvailableChalets($searchParams);

        if (!$results['success']) {
            $this->error("Search failed:");
            foreach ($results['errors'] as $error) {
                $this->line("- {$error}");
            }
            return 1;
        }

        $this->info("Found {$results['total_count']} available chalets.");

        if ($results['total_count'] > 0) {
            $this->table(
                ['ID', 'Name', 'City', 'Min Price', 'Max Price', 'Slots'],
                array_map(function ($chalet) {
                    return [
                        $chalet['chalet_id'],
                        $chalet['name'],
                        $chalet['location']['city'],
                        $chalet['pricing']['min_price'],
                        $chalet['pricing']['max_price'],
                        count($chalet['availability']['available_slots']),
                    ];
                }, $results['chalets'])
            );
        }

        return 0;
    }
}
