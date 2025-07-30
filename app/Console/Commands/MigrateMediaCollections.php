<?php

namespace App\Console\Commands;

use App\Models\Chalet;
use Illuminate\Console\Command;

class MigrateMediaCollections extends Command
{
    protected $signature = 'media:migrate-collections';
    protected $description = 'Migrate media from default collection to gallery collection';

    public function handle()
    {
        $this->info('Starting media collection migration...');

        $chalets = Chalet::all();
        $count = 0;

        foreach ($chalets as $chalet) {
            // Get all media from default collection
            $defaultMedia = $chalet->getMedia('default');
            
            foreach ($defaultMedia as $media) {
                // Move to new gallery collection
                $media->move($chalet, 'gallery');
                $count++;
            }
        }

        $this->info("Migration completed! Moved {$count} media items to 'gallery' collection.");
    }
}
