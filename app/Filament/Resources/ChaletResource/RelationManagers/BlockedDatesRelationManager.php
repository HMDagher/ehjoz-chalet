<?php

declare(strict_types=1);

namespace App\Filament\Resources\ChaletResource\RelationManagers;

use App\Enums\BlockReason;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

final class BlockedDatesRelationManager extends RelationManager
{
    protected static string $relationship = 'BlockedDates';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\DatePicker::make('date')
                    ->required(),
                Forms\Components\Select::make('time_slot_id')
                    ->label('Time Slot')
                    ->options(function (RelationManager $livewire) {
                        return $livewire->ownerRecord->timeSlots()->pluck('name', 'id');
                    })
                    ->searchable(),
                Forms\Components\Select::make('reason')
                    ->options(BlockReason::class)
                    ->native(false)
                    ->required(),
                Forms\Components\Textarea::make('notes')
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('date')
            ->columns([
                Tables\Columns\TextColumn::make('date')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('timeSlot.name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('reason')
                    ->badge()
                    ->searchable()
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
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
}
