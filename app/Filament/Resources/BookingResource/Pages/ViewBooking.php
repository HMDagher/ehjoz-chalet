<?php

declare(strict_types=1);

namespace App\Filament\Resources\BookingResource\Pages;

use App\Filament\Resources\BookingResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

final class ViewBooking extends ViewRecord
{
    protected static string $resource = BookingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
            Actions\Action::make('addPayment')
                ->label('Add Payment')
                ->icon('heroicon-o-banknotes')
                ->form([
                    \Filament\Forms\Components\TextInput::make('payment_reference')->required(),
                    \Filament\Forms\Components\TextInput::make('amount')->numeric()->required(),
                    \Filament\Forms\Components\Select::make('payment_method')
                        ->options(\App\Enums\PaymentMethod::class)
                        ->required(),
                    \Filament\Forms\Components\DateTimePicker::make('paid_at')->required(),
                    \Filament\Forms\Components\Select::make('status')
                        ->options(\App\Enums\PaymentStatus::class)
                        ->required(),
                    \Filament\Forms\Components\Textarea::make('notes'),
                ])
                ->action(function ($record, array $data) {
                    $payment = $record->payment()->create([
                        'payment_reference' => $data['payment_reference'],
                        'amount' => $data['amount'],
                        'payment_method' => $data['payment_method'],
                        'paid_at' => $data['paid_at'],
                        'status' => $data['status'],
                        'notes' => $data['notes'] ?? null,
                    ]);
                    // Recalculate earnings and remaining payment
                    $platformCommission = $record->platform_commission;
                    $discountAmount = $record->discount_amount;
                    $baseSlotPrice = $record->base_slot_price;
                    $seasonalAdjustment = $record->seasonal_adjustment;
                    $extraHoursAmount = $record->extra_hours_amount;
                    $paymentAmount = $payment->amount;
                    $ownerEarning = $paymentAmount - $platformCommission;
                    $platformEarning = $platformCommission - $discountAmount;
                    $remainingPayment = $baseSlotPrice + $seasonalAdjustment + $extraHoursAmount - $platformCommission - $paymentAmount;
                    $record->update([
                        'owner_earning' => $ownerEarning,
                        'platform_earning' => $platformEarning,
                        'remaining_payment' => $remainingPayment,
                    ]);
                    // Update booking status/payment_status
                    if (in_array($data['status'], ['paid', 'partial'])) {
                        $record->update([
                            'status' => 'confirmed',
                            'payment_status' => $data['status'],
                        ]);
                    } else {
                        $record->update([
                            'payment_status' => $data['status'],
                        ]);
                    }
                    \Filament\Notifications\Notification::make()
                        ->title('Payment added successfully!')
                        ->success()
                        ->send();
                })
                ->modalHeading('Add Payment')
                ->modalButton('Add Payment'),
        ];
    }
}
