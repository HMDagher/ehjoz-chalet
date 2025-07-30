<?php

namespace App\Console\Commands;

use App\Models\Chalet;
use Illuminate\Console\Command;

class DeleteChaletDefaultMediaCommand extends Command
{
    protected $signature = 'chalet:delete-default-media';

    protected $description = 'Delete all media from the default collection for all chalets';

    public function handle()
    {
        $this->info('Starting to delete default media from chalets...');

        $count = 0;
        Chalet::chunk(100, function ($chalets) use (&$count) {
            foreach ($chalets as $chalet) {
                $chalet->clearMediaCollection();
                $count++;
            }
        });

        $this->info("Successfully cleared default media collection from {$count} chalets.");
    }
}
