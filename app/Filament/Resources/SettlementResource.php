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
                    ->required(),
                Forms\Components\TextInput::make('settlement_reference')
                    ->required(),
                Forms\Components\DatePicker::make('period_start')
                    ->required(),
                Forms\Components\DatePicker::make('period_end')
                    ->required(),
                Forms\Components\TextInput::make('total_bookings')
                    ->required()
                    ->numeric(),
                Forms\Components\TextInput::make('gross_amount')
                    ->required()
                    ->numeric(),
                Forms\Components\TextInput::make('commission_amount')
                    ->required()
                    ->numeric(),
                Forms\Components\TextInput::make('net_amount')
                    ->required()
                    ->numeric(),
                Forms\Components\Select::make('status')
                    ->options(SettlementStatus::class)
                    ->native(false)
                    ->required(),
                Forms\Components\DateTimePicker::make('paid_at'),
                Forms\Components\TextInput::make('payment_reference'),
                Forms\Components\Textarea::make('notes')
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('chalet.name')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('settlement_reference')
                    ->searchable(),
                Tables\Columns\TextColumn::make('period_start')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('period_end')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('total_bookings')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('gross_amount')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('commission_amount')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('net_amount')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->searchable(),
                Tables\Columns\TextColumn::make('paid_at')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('payment_reference')
                    ->searchable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([
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
