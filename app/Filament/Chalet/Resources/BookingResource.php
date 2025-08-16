<?php

namespace App\Filament\Chalet\Resources;

use App\Filament\Chalet\Resources\BookingResource\Pages;
use App\Models\Booking;
use Filament\Infolists\Components\Grid;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\Tabs;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
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
                                Section::make('Customer Information')
                                    ->schema([
                                        Grid::make(2)->schema([
                                            TextEntry::make('user.name')->label('Customer Name'),
                                            TextEntry::make('user.email')->label('Customer Email'),
                                        ]),
                                    ]),
                                Section::make('Booking Details')
                                    ->schema([
                                        Grid::make(2)->schema([
                                            TextEntry::make('booking_reference')->label('Booking Reference'),
                                            TextEntry::make('chalet.name')->label('Chalet'),
                                            TextEntry::make('start_date')->dateTime()->label('Check-in'),
                                            TextEntry::make('end_date')->dateTime()->label('Check-out'),
                                            TextEntry::make('booking_type')->label('Booking Type'),
                                            // TextEntry::make('adults_count')->label('Adults'),
                                            // TextEntry::make('children_count')->label('Children'),
                                            // TextEntry::make('total_guests')->label('Total Guests'),
                                            // TextEntry::make('special_requests')->label('Special Requests')->columnSpanFull(),
                                        ]),
                                    ]),
                                Section::make('Pricing')
                                    ->schema([
                                        Grid::make(2)->schema([
                                            TextEntry::make('base_slot_price')->money('USD')->label('Base Price'),
                                            TextEntry::make('seasonal_adjustment')->money('USD')->label('Seasonal Adjustment'),
                                            TextEntry::make('extra_hours_amount')->money('USD')->label('Extra Hours'),
                                            TextEntry::make('discount_amount')->money('USD')->label('Discount'),
                                            TextEntry::make('discount_reason')->label('Discount Reason'),
                                            TextEntry::make('total_amount')->money('USD')->label('Total Amount'),
                                            TextEntry::make('platform_commission')->money('USD')->label('Platform Commission'),
                                            TextEntry::make('owner_earning')->money('USD')->label('Owner Earning'),
                                            TextEntry::make('platform_earning')->money('USD')->label('Platform Earning'),
                                            TextEntry::make('remaining_payment')->money('USD')->label('Remaining Payment'),
                                        ]),
                                    ]),
                                Section::make('Payment Details')
                                    ->schema([
                                        Grid::make(2)->schema([
                                            TextEntry::make('payment.payment_reference')->label('Payment Reference'),
                                            TextEntry::make('payment.amount')->money('USD')->label('Payment Amount'),
                                            TextEntry::make('payment.payment_method')->badge()->label('Payment Method'),
                                            TextEntry::make('payment.status')->badge()->label('Payment Status'),
                                            TextEntry::make('payment.paid_at')->dateTime()->label('Paid At'),
                                            TextEntry::make('payment.notes')->label('Payment Notes')->columnSpanFull(),
                                        ]),
                                    ]),
                                Section::make('Status and Notes')
                                    ->schema([
                                        Grid::make(2)->schema([
                                            TextEntry::make('status')->badge()->label('Booking Status'),
                                            TextEntry::make('payment_status')->badge()->label('Payment Status'),
                                        ]),
                                        TextEntry::make('internal_notes')->label('Internal Notes')->columnSpanFull(),
                                    ]),
                                Section::make('Cancellation')
                                    ->schema([
                                        Grid::make(2)->schema([
                                            TextEntry::make('cancelled_at')->dateTime()->label('Cancelled At'),
                                            TextEntry::make('cancellation_reason')->label('Cancellation Reason'),
                                        ]),
                                    ])->collapsible(),
                            ]),
                        Tabs\Tab::make('Payment')
                            ->schema([
                                Section::make('Payment Details')
                                    ->schema([
                                        TextEntry::make('payment.payment_reference')->label('Payment Reference'),
                                        TextEntry::make('payment.amount')->money('USD')->label('Payment Amount'),
                                        TextEntry::make('payment.payment_method')->badge()->label('Payment Method'),
                                        TextEntry::make('payment.status')->badge()->label('Payment Status'),
                                        TextEntry::make('payment.paid_at')->dateTime()->label('Paid At'),
                                        TextEntry::make('payment.notes')->label('Payment Notes')->columnSpanFull(),
                                    ]),
                            ]),
                    ])->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('booking_reference')->label('Reference')->searchable(),
                Tables\Columns\TextColumn::make('user.name')->label('Customer')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('chalet.name')->label('Chalet')->sortable(),
                Tables\Columns\TextColumn::make('start_date')->label('Check-in')->dateTime()->sortable(),
                Tables\Columns\TextColumn::make('end_date')->label('Check-out')->dateTime()->sortable(),
                Tables\Columns\TextColumn::make('total_amount')->label('Total')->money('USD')->sortable(),
                Tables\Columns\TextColumn::make('payment_status')->badge()->sortable(),
                Tables\Columns\TextColumn::make('owner_earning')->label('Owner Earning')->money('USD')->sortable(),
                Tables\Columns\TextColumn::make('remaining_payment')->label('Remaining')->money('USD')->sortable(),
                Tables\Columns\TextColumn::make('status')->badge()->sortable(),
            ])
            ->defaultSort('start_date', 'desc')
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
