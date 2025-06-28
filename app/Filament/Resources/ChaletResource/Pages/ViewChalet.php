<?php

declare(strict_types=1);

namespace App\Filament\Resources\ChaletResource\Pages;

use App\Filament\Resources\ChaletResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

final class ViewChalet extends ViewRecord
{
    protected static string $resource = ChaletResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
