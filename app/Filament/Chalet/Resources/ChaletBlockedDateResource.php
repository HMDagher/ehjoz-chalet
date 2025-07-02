<?php

namespace App\Filament\Chalet\Resources;

use App\Filament\Chalet\Resources\ChaletBlockedDateResource\Pages;
use App\Filament\Chalet\Resources\ChaletBlockedDateResource\RelationManagers;
use App\Models\Chalet;
use App\Models\ChaletBlockedDate;
use App\Models\ChaletTimeSlot;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Enums\BlockReason;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

class ChaletBlockedDateResource extends Resource
{
    protected static ?string $model = ChaletBlockedDate::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->whereHas('chalet', function (Builder $query) {
            $query->where('owner_id', Auth::id());
        });
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Hidden::make('chalet.id')
                    ->default(function () {
                        return auth()->user()?->chalet?->id;
                    }),
                Forms\Components\DatePicker::make('date')
                    ->required()
                    ->live(),
                Forms\Components\Select::make('time_slot_id')
                    ->label('Time Slot')
                    ->options(function ($get): Collection {
                        $chaletId = auth()->user()->chalet?->id;
                        $date = $get('date');
                        if (!$chaletId || !$date) {
                            return collect();
                        }
                        $allSlots = \App\Models\ChaletTimeSlot::where('chalet_id', $chaletId)->get();
                        $bookings = \App\Models\Booking::where('chalet_id', $chaletId)
                            ->whereDate('start_date', '<=', $date)
                            ->whereDate('end_date', '>=', $date)
                            ->with('timeSlots')
                            ->get();
                        $bookedSlotIds = collect();
                        $hasOvernightBooking = false;
                        foreach ($bookings as $booking) {
                            foreach ($booking->timeSlots as $slot) {
                                $bookedSlotIds->push($slot->id);
                                if ($slot->is_overnight) {
                                    $hasOvernightBooking = true;
                                }
                            }
                        }
                        if ($hasOvernightBooking) {
                            return collect();
                        }
                        if ($allSlots->where('is_overnight', false)->count() > 0 && $allSlots->where('is_overnight', false)->pluck('id')->diff($bookedSlotIds)->isEmpty()) {
                            return collect();
                        }
                        // Label overnight slots
                        return $allSlots->filter(function ($slot) use ($bookedSlotIds) {
                            return !$bookedSlotIds->contains($slot->id);
                        })->mapWithKeys(function ($slot) {
                            $label = $slot->name;
                            if ($slot->is_overnight) {
                                $label .= ' (Overnight)';
                            }
                            return [$slot->id => $label];
                        });
                    })
                    ->placeholder('Block entire day (leave empty)')
                    ->helperText('Leave empty to block the entire day. Select a slot to block only that slot. Overnight slots are labeled.')
                    ->searchable(),
                Forms\Components\Select::make('reason')
                    ->options(BlockReason::class)
                    ->required()
                    ->default(BlockReason::ExternalBooking),
                Forms\Components\Textarea::make('notes')
                    ->columnSpanFull(),
            ])->columns(3);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('date')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('timeSlot.name')
                    ->sortable(),
                Tables\Columns\TextColumn::make('reason')
                    ->badge(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageChaletBlockedDates::route('/'),
        ];
    }
}
