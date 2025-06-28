<?php

namespace App\Filament\Chalet\Resources;

use App\Filament\Chalet\Resources\BookingResource\Pages;
use App\Filament\Chalet\Resources\BookingResource\RelationManagers;
use App\Models\Booking;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use Filament\Infolists\Components\Grid;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\Tabs;
use Filament\Infolists\Components\TextEntry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Auth;

class BookingResource extends Resource
{
    protected static ?string $model = Booking::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $recordTitleAttribute = 'booking_reference';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->whereHas('chalet', function (Builder $query) {
            $query->where('owner_id', Auth::id());
        });
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Tabs::make('Booking')
                    ->tabs([
                        Tabs\Tab::make('Booking Details')
                            ->schema([
                                Section::make()
                                    ->schema([
                                        Grid::make(2)->schema([
                                            TextEntry::make('user.name'),
                                            TextEntry::make('booking_reference')
                                                ->columnSpanFull(),
                                        ]),
                                    ]),
                                Section::make('Date and Time')
                                    ->schema([
                                        Grid::make(2)->schema([
                                            TextEntry::make('start_date')
                                                ->dateTime(),
                                            TextEntry::make('end_date')
                                                ->dateTime(),
                                            TextEntry::make('extra_hours'),
                                        ]),
                                    ]),
                                Section::make('Guest Information')
                                    ->schema([
                                        Grid::make(3)->schema([
                                            TextEntry::make('adults_count'),
                                            TextEntry::make('children_count'),
                                            TextEntry::make('total_guests'),
                                        ]),
                                    ]),
                                Section::make('Pricing')
                                    ->schema([
                                        Grid::make(2)->schema([
                                            TextEntry::make('base_slot_price')->money('USD'),
                                            TextEntry::make('seasonal_adjustment')->money('USD'),
                                            TextEntry::make('extra_hours_amount')->money('USD'),
                                            TextEntry::make('platform_commission')->money('USD'),
                                            TextEntry::make('total_amount')->money('USD'),
                                        ]),
                                    ]),
                                Section::make('Status and Notes')
                                    ->schema([
                                        Grid::make(2)->schema([
                                            TextEntry::make('status')->badge(),
                                            TextEntry::make('payment_status')->badge(),
                                        ]),
                                        TextEntry::make('special_requests')->columnSpanFull(),
                                        TextEntry::make('internal_notes')->columnSpanFull(),
                                    ]),
                                Section::make('Cancellation')
                                    ->schema([
                                        Grid::make(2)->schema([
                                            TextEntry::make('cancelled_at')->dateTime(),
                                            TextEntry::make('cancellation_reason'),
                                        ]),
                                    ])->collapsible(),
                            ]),
                        Tabs\Tab::make('Payment')
                            ->schema([
                                Section::make('Payment Details')
                                    ->schema([
                                        TextEntry::make('payment.payment_reference'),
                                        TextEntry::make('payment.amount')->money('USD'),
                                        TextEntry::make('payment.payment_method')->badge(),
                                        TextEntry::make('payment.status')->badge(),
                                        TextEntry::make('payment.paid_at')->dateTime(),
                                        TextEntry::make('payment.notes')->columnSpanFull(),
                                    ]),
                            ]),
                    ])->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Customer')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('start_date')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('end_date')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('total_amount')
                    ->money('USD')
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->searchable(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBookings::route('/'),
        ];
    }
}
