<?php

declare(strict_types=1);

namespace App\Filament\Resources\ChaletResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

final class TimeSlotsRelationManager extends RelationManager
{
    protected static string $relationship = 'timeSlots';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Time Slot Details')
                    ->columns(2)
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(100)
                            ->columnSpanFull(),
                        Forms\Components\TimePicker::make('start_time')
                            ->required()
                            ->seconds(false),
                        Forms\Components\TimePicker::make('end_time')
                            ->required()
                            ->seconds(false),
                        Forms\Components\TextInput::make('duration_hours')
                            ->required()
                            ->numeric(),
                        Forms\Components\Toggle::make('is_overnight')
                            ->required(),
                    ]),
                Forms\Components\Section::make('Pricing')
                    ->columns(2)
                    ->schema([
                        Forms\Components\TextInput::make('weekday_price')
                            ->required()
                            ->numeric()
                            ->prefix('$'),
                        Forms\Components\TextInput::make('weekend_price')
                            ->required()
                            ->numeric()
                            ->prefix('$'),
                    ]),
                Forms\Components\Section::make('Extra Hours')
                    ->columns(2)
                    ->schema([
                        Forms\Components\Toggle::make('allows_extra_hours')
                            ->required()
                            ->reactive(),
                        Forms\Components\TextInput::make('extra_hour_price')
                            ->numeric()
                            ->prefix('$')
                            ->visible(fn (callable $get) => $get('allows_extra_hours')),
                        Forms\Components\TextInput::make('max_extra_hours')
                            ->numeric()
                            ->visible(fn (callable $get) => $get('allows_extra_hours')),
                    ]),
                Forms\Components\Section::make('Availability')
                    ->schema([
                        Forms\Components\CheckboxList::make('available_days')
                            ->required()
                            ->options([
                                'monday' => 'Monday',
                                'tuesday' => 'Tuesday',
                                'wednesday' => 'Wednesday',
                                'thursday' => 'Thursday',
                                'friday' => 'Friday',
                                'saturday' => 'Saturday',
                                'sunday' => 'Sunday',
                            ])
                            ->columns(3),
                        Forms\Components\Toggle::make('is_active')
                            ->required()
                            ->default(true),
                    ]),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable(),
                Tables\Columns\TextColumn::make('start_time')->sortable(),
                Tables\Columns\TextColumn::make('end_time')->sortable(),
                Tables\Columns\TextColumn::make('weekday_price')->money('USD')->sortable(),
                Tables\Columns\TextColumn::make('weekend_price')->money('USD')->sortable(),
                Tables\Columns\IconColumn::make('is_active')->boolean(),
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
