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
                    ->afterStateUpdated(fn ($state, callable $set, callable $get) => self::calculateSettlementFields($set, $get)),
                Forms\Components\TextInput::make('settlement_reference')
                    ->required(),
                Forms\Components\DatePicker::make('period_start')
                    ->required()
                    ->reactive()
                    ->afterStateUpdated(fn ($state, callable $set, callable $get) => self::calculateSettlementFields($set, $get)),
                Forms\Components\DatePicker::make('period_end')
                    ->required()
                    ->reactive()
                    ->afterStateUpdated(fn ($state, callable $set, callable $get) => self::calculateSettlementFields($set, $get)),
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
                Forms\Components\Placeholder::make('bookings_summary')
                    ->label('Included Bookings')
                    ->content(function ($get) {
                        $chaletId = $get('chalet_id');
                        $start = $get('period_start');
                        $end = $get('period_end');
                        if (!$chaletId || !$start || !$end) {
                            return 'Select a chalet and period to see included bookings.';
                        }
                        $bookings = \App\Models\Booking::query()
                            ->where('chalet_id', $chaletId)
                            ->whereDate('start_date', '>=', $start)
                            ->whereDate('end_date', '<=', $end)
                            ->whereIn('status', ['confirmed', 'completed'])
                            ->get();
                        if ($bookings->isEmpty()) {
                            return 'No bookings found for this period.';
                        }
                        $html = '<div style="overflow-x:auto"><table class="table-auto w-full text-xs"><thead><tr>';
                        $columns = ['Reference', 'Check-in', 'Check-out', 'Base', 'Seasonal', 'Extra', 'Commission', 'Discount', 'Paid', 'Owner', 'Platform', 'Remaining'];
                        foreach ($columns as $col) {
                            $html .= '<th class="px-2 py-1">' . $col . '</th>';
                        }
                        $html .= '</tr></thead><tbody>';
                        foreach ($bookings as $b) {
                            $html .= '<tr>';
                            $html .= '<td class="px-2 py-1">' . $b->booking_reference . '</td>';
                            $html .= '<td class="px-2 py-1">' . ($b->start_date ? $b->start_date->format('Y-m-d') : '-') . '</td>';
                            $html .= '<td class="px-2 py-1">' . ($b->end_date ? $b->end_date->format('Y-m-d') : '-') . '</td>';
                            $html .= '<td class="px-2 py-1">' . number_format((float) $b->base_slot_price, 2) . '</td>';
                            $html .= '<td class="px-2 py-1">' . number_format((float) $b->seasonal_adjustment, 2) . '</td>';
                            $html .= '<td class="px-2 py-1">' . number_format((float) $b->extra_hours_amount, 2) . '</td>';
                            $html .= '<td class="px-2 py-1">' . number_format((float) $b->platform_commission, 2) . '</td>';
                            $html .= '<td class="px-2 py-1">' . number_format((float) $b->discount_amount, 2) . '</td>';
                            $html .= '<td class="px-2 py-1">' . ($b->payment ? number_format((float) $b->payment->amount, 2) : '-') . '</td>';
                            $html .= '<td class="px-2 py-1">' . number_format((float) $b->owner_earning, 2) . '</td>';
                            $html .= '<td class="px-2 py-1">' . number_format((float) $b->platform_earning, 2) . '</td>';
                            $html .= '<td class="px-2 py-1">' . number_format((float) $b->remaining_payment, 2) . '</td>';
                            $html .= '</tr>';
                        }
                        $html .= '</tbody></table></div>';
                        return $html;
                    })
                    ->columnSpanFull()
                    ->extraAttributes(['class' => 'prose max-w-none']),
            ]);
    }

    public static function calculateSettlementFields(callable $set, callable $get)
    {
        $chaletId = $get('chalet_id');
        $start = $get('period_start');
        $end = $get('period_end');
        if (!$chaletId || !$start || !$end) {
            $set('total_bookings', 0);
            $set('gross_amount', 0);
            $set('commission_amount', 0);
            $set('net_amount', 0);
            return;
        }
        $bookings = \App\Models\Booking::query()
            ->where('chalet_id', $chaletId)
            ->whereDate('start_date', '>=', $start)
            ->whereDate('end_date', '<=', $end)
            ->whereIn('status', ['confirmed', 'completed'])
            ->get();
        $totalBookings = $bookings->count();
        $grossAmount = $bookings->sum(fn($b) => $b->base_slot_price + $b->seasonal_adjustment + $b->extra_hours_amount);
        $commissionAmount = $bookings->sum('platform_commission');
        $netAmount = $bookings->sum('owner_earning');
        $set('total_bookings', $totalBookings);
        $set('gross_amount', $grossAmount);
        $set('commission_amount', $commissionAmount);
        $set('net_amount', $netAmount);
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

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageSettlements::route('/'),
        ];
    }
}
