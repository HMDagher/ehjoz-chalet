<?php

namespace App\Filament\Customer\Resources\BookingResource\Pages;

use App\Filament\Customer\Resources\BookingResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageBookings extends ManageRecords
{
    protected static string $resource = BookingResource::class;
}
