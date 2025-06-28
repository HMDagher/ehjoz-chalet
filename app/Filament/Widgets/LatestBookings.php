<?php

namespace App\Filament\Widgets;

use App\Models\Booking;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class LatestBookings extends BaseWidget
{
    protected static ?int $sort = 3;

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Booking::query()->latest('created_at')->limit(10)
            )
            ->columns([
                Tables\Columns\TextColumn::make('chalet.name')
                    ->label('Chalet'),
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Customer'),
                Tables\Columns\TextColumn::make('status')
                    ->badge(),
                Tables\Columns\TextColumn::make('total_amount')
                    ->label('Total')
                    ->money('USD', true),
            ]);
    }
}
