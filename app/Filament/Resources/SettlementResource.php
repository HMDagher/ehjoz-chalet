<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Enums\SettlementStatus;
use App\Filament\Resources\SettlementResource\Pages;
use App\Models\Settlement;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\Grid;
use Filament\Infolists\Components\TextEntry;
use Filament\Forms\Components\Repeater;

final class SettlementResource extends Resource
{
    protected static ?string $model = Settlement::class;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationGroup = 'Financials';

    protected static ?string $recordTitleAttribute = 'settlement_reference';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('chalet_id')
                    ->relationship('chalet', 'name')
                    ->required()
                    ->reactive()
                    ->afterStateUpdated(fn ($state, callable $set, callable $get) => self::updateBookingsAndCalculateFields($set, $get)),
                Forms\Components\TextInput::make('settlement_reference')
                    ->required(),
                Forms\Components\DatePicker::make('period_start')
                    ->required()
                    ->reactive()
                    ->afterStateUpdated(fn ($state, callable $set, callable $get) => self::updateBookingsAndCalculateFields($set, $get)),
                Forms\Components\DatePicker::make('period_end')
                    ->required()
                    ->reactive()
                    ->afterStateUpdated(fn ($state, callable $set, callable $get) => self::updateBookingsAndCalculateFields($set, $get)),
                Forms\Components\TextInput::make('total_bookings')
                    ->required()
                    ->numeric()
                    ->readOnly(),
                Forms\Components\TextInput::make('gross_amount')
                    ->required()
                    ->numeric()
                    ->readOnly(),
                Forms\Components\TextInput::make('commission_amount')
                    ->required()
                    ->numeric()
                    ->readOnly(),
                Forms\Components\TextInput::make('net_amount')
                    ->required()
                    ->numeric()
                    ->readOnly(),
                Forms\Components\Select::make('status')
                    ->options(SettlementStatus::class)
                    ->native(false)
                    ->required(),
                Forms\Components\DateTimePicker::make('paid_at'),
                Forms\Components\TextInput::make('payment_reference'),
                Forms\Components\Textarea::make('notes')
                    ->columnSpanFull(),
                Forms\Components\Repeater::make('included_bookings')
                    ->label('Included Bookings')
                    ->schema([
                        Forms\Components\TextInput::make('booking_reference')
                            ->label('Reference')
                            ->readOnly(),
                        Forms\Components\TextInput::make('payment_amount')
                            ->label('Paid')
                            ->readOnly(),
                        Forms\Components\TextInput::make('owner_earning')
                            ->label('Owner')
                            ->readOnly(),
                    ])
                    ->columns(3)
                    ->columnSpanFull()
                    ->disabled()
                    ->deletable(false)
                    ->addable(false)
                    ->reorderable(false),
            ]);
    }

    public static function updateBookingsAndCalculateFields(callable $set, callable $get): void
    {
        $chaletId = $get('chalet_id');
        $start = $get('period_start');
        $end = $get('period_end');
        
        if (!$chaletId || !$start || !$end) {
            // Reset all fields when required data is missing
            $set('total_bookings', 0);
            $set('gross_amount', 0);
            $set('commission_amount', 0);
            $set('net_amount', 0);
            $set('included_bookings', []);
            return;
        }

        // Fetch bookings for the selected chalet and period
        $bookings = \App\Models\Booking::query()
            ->where('chalet_id', $chaletId)
            ->whereDate('start_date', '>=', $start)
            ->whereDate('end_date', '<=', $end)
            ->whereIn('status', ['confirmed', 'completed'])
            ->get();

        // Calculate settlement fields
        $totalBookings = $bookings->count();
        $grossAmount = $bookings->sum(fn($b) => $b->base_slot_price + $b->seasonal_adjustment + $b->extra_hours_amount);
        $commissionAmount = $bookings->sum('platform_commission');
        $netAmount = $bookings->sum('owner_earning');

        // Update the calculated fields
        $set('total_bookings', $totalBookings);
        $set('gross_amount', $grossAmount);
        $set('commission_amount', $commissionAmount);
        $set('net_amount', $netAmount);

        // Update the repeater with booking data
        $bookingData = $bookings->map(function ($booking) {
            return [
                'booking_reference' => $booking->booking_reference,
                'start_date' => optional($booking->start_date)->format('Y-m-d'),
                'end_date' => optional($booking->end_date)->format('Y-m-d'),
                'base_slot_price' => (float) $booking->base_slot_price,
                'seasonal_adjustment' => (float) $booking->seasonal_adjustment,
                'extra_hours_amount' => (float) $booking->extra_hours_amount,
                'platform_commission' => (float) $booking->platform_commission,
                'discount_amount' => (float) $booking->discount_amount,
                'payment_amount' => $booking->payment ? (float) $booking->payment->amount : 0,
                'owner_earning' => (float) $booking->owner_earning,
                'platform_earning' => (float) $booking->platform_earning,
                'remaining_payment' => (float) $booking->remaining_payment,
            ];
        })->toArray();

        $set('included_bookings', $bookingData);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('chalet.name')->label('Chalet')->sortable(),
                Tables\Columns\TextColumn::make('settlement_reference')->label('Reference')->searchable(),
                Tables\Columns\TextColumn::make('period_start')->date()->sortable(),
                Tables\Columns\TextColumn::make('period_end')->date()->sortable(),
                Tables\Columns\TextColumn::make('total_bookings')->sortable(),
                Tables\Columns\TextColumn::make('gross_amount')->money('USD')->sortable(),
                Tables\Columns\TextColumn::make('commission_amount')->money('USD')->sortable(),
                Tables\Columns\TextColumn::make('net_amount')->money('USD')->sortable(),
                Tables\Columns\TextColumn::make('status')->badge()->sortable(),
                Tables\Columns\TextColumn::make('paid_at')->dateTime()->sortable(),
                Tables\Columns\TextColumn::make('payment_reference')->searchable(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Section::make('Included Bookings')
                    ->schema([
                        \Filament\Infolists\Components\RepeatableEntry::make('included_bookings')
                            ->label('Included Bookings')
                            ->schema([
                                TextEntry::make('booking_reference')->label('Reference'),
                                TextEntry::make('start_date')->label('Check-in'),
                                TextEntry::make('end_date')->label('Check-out'),
                                TextEntry::make('base_slot_price')->label('Base'),
                                TextEntry::make('seasonal_adjustment')->label('Seasonal'),
                                TextEntry::make('extra_hours_amount')->label('Extra'),
                                TextEntry::make('platform_commission')->label('Commission'),
                                TextEntry::make('discount_amount')->label('Discount'),
                                TextEntry::make('payment_amount')->label('Paid'),
                                TextEntry::make('owner_earning')->label('Owner'),
                                TextEntry::make('platform_earning')->label('Platform'),
                                TextEntry::make('remaining_payment')->label('Remaining'),
                            ])
                            ->columns(3),
                    ])
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageSettlements::route('/'),
        ];
    }
}