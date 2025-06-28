<?php

namespace App\Filament\Chalet\Resources\ChaletBlockedDateResource\Pages;

use App\Filament\Chalet\Resources\ChaletBlockedDateResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageChaletBlockedDates extends ManageRecords
{
    protected static string $resource = ChaletBlockedDateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
