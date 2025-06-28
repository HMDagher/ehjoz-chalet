<?php

namespace App\Filament\Chalet\Resources\BookingResource\Pages;

use App\Filament\Chalet\Resources\BookingResource;
use Filament\Resources\Pages\ListRecords;

class ListBookings extends ListRecords
{
    protected static string $resource = BookingResource::class;
}
