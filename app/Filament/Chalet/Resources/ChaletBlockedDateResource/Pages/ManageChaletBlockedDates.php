<?php

namespace App\Filament\Chalet\Resources\ChaletBlockedDateResource\Pages;

use App\Filament\Chalet\Resources\ChaletBlockedDateResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageChaletBlockedDates extends ManageRecords
{
    protected static string $resource = ChaletBlockedDateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
            ->label('Block Date'),
            Actions\Action::make('blockDateRange')
                ->label('Block Date Range')
                ->icon('heroicon-o-calendar-days')
                ->outlined()
                ->form([
                    \Filament\Forms\Components\DatePicker::make('start_date')
                        ->label('Start Date')
                        ->required(),
                    \Filament\Forms\Components\DatePicker::make('end_date')
                        ->label('End Date')
                        ->required(),
                    \Filament\Forms\Components\Textarea::make('notes')
                        ->label('Notes')
                        ->columnSpanFull(),
                ])
                ->action(function (array $data) {
                    $user = auth()->user();
                    $chalet = $user?->chalet;
                    if (!$chalet) {
                        \Filament\Notifications\Notification::make()
                            ->title('No chalet found for user!')
                            ->danger()
                            ->send();
                        return;
                    }
                    $overnightSlot = $chalet->timeSlots()->where('is_overnight', true)->first();
                    if (!$overnightSlot) {
                        \Filament\Notifications\Notification::make()
                            ->title('No overnight time slot found!')
                            ->danger()
                            ->send();
                        return;
                    }
                    $start = \Carbon\Carbon::parse($data['start_date']);
                    $end = \Carbon\Carbon::parse($data['end_date']);
                    if ($end->lessThan($start)) {
                        \Filament\Notifications\Notification::make()
                            ->title('End date must be after start date!')
                            ->danger()
                            ->send();
                        return;
                    }
                    for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
                        \App\Models\ChaletBlockedDate::firstOrCreate([
                            'chalet_id' => $chalet->id,
                            'date' => $date->toDateString(),
                            'time_slot_id' => $overnightSlot->id,
                        ], [
                            'reason' => \App\Enums\BlockReason::ExternalBooking,
                            'notes' => $data['notes'] ?? null,
                        ]);
                    }
                    \Filament\Notifications\Notification::make()
                        ->title('Blocked dates created successfully!')
                        ->success()
                        ->send();
                })
                ->modalHeading('Block a Range of Dates')
                ->modalButton('Block Dates'),
        ];
    }
}
