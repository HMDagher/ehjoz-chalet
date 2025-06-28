<?php

declare(strict_types=1);

namespace App\Filament\Resources\ChaletResource\Pages;

use App\Filament\Resources\ChaletResource;
use Filament\Resources\Pages\CreateRecord;

final class CreateChalet extends CreateRecord
{
    protected static string $resource = ChaletResource::class;
}
