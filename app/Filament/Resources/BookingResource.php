<?php

namespace App\Filament\Resources;

use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Filament\Resources\BookingResource\Pages;
use App\Models\Booking;
use Filament\Forms;
use Filament\Forms\Components\Section;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Forms\Components\Fieldset;

final class BookingResource extends Resource
{
    protected static ?string $model = Booking::class;

    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';

    protected static ?string $navigationGroup = 'Chalet Management';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Booking Details')
                    ->columns(2)
                    ->schema([
                        Forms\Components\Select::make('chalet_id')
                            ->relationship('chalet', 'name')
                            ->required(),
                        Forms\Components\Select::make('user_id')
                            ->relationship('user', 'name')
                            ->required(),
                        Forms\Components\Select::make('timeSlots')
                            ->label('Time Slots')
                            ->relationship(
                                name: 'timeSlots',
                                titleAttribute: 'name',
                                modifyQueryUsing: fn ($query) => $query->select('chalet_time_slots.id', 'chalet_time_slots.name')
                            )
                            ->multiple()
                            ->preload(),
                        Forms\Components\TextInput::make('booking_reference')
                            ->required(),
                        Forms\Components\DateTimePicker::make('start_date')
                            ->required(),
                        Forms\Components\DateTimePicker::make('end_date')
                            ->required(),
                        Fieldset::make('Guests')
                            ->schema([
                                Forms\Components\TextInput::make('adults_count')
                            ->required()
                            ->numeric()
                            ->default(1),
                        Forms\Components\TextInput::make('children_count')
                            ->required()
                            ->numeric(),
                        Forms\Components\TextInput::make('total_guests')
                            ->required()
                            ->numeric()
                            ->default(1),
                            ])->columns(3),
                        Forms\Components\TextInput::make('base_slot_price')
                            ->required()
                            ->numeric(),
                        Forms\Components\TextInput::make('seasonal_adjustment')
                            ->numeric()
                            ->default(0),
                        Forms\Components\TextInput::make('extra_hours')
                            ->numeric(),
                        Forms\Components\TextInput::make('extra_hours_amount')
                            ->numeric()
                            ->default(0),
                        Forms\Components\TextInput::make('platform_commission')
                            ->required()
                            ->numeric(),
                        Forms\Components\TextInput::make('total_amount')
                            ->required()
                            ->numeric(),
                        Forms\Components\Select::make('status')
                            ->options(BookingStatus::class)
                            ->native(false)
                            ->required(),
                        Forms\Components\Select::make('payment_status')
                            ->options(PaymentStatus::class)
                            ->native(false)
                            ->required(),
                    ]),
                Section::make('Additional Information')
                    ->columns(2)
                    ->schema([
                        Forms\Components\Textarea::make('special_requests')
                            ->columnSpanFull(),
                        Forms\Components\Textarea::make('internal_notes')
                            ->columnSpanFull(),
                        Forms\Components\Textarea::make('cancellation_reason')
                            ->columnSpanFull(),
                        Forms\Components\DateTimePicker::make('cancelled_at'),
                        Forms\Components\DateTimePicker::make('auto_completed_at'),
                    ]),
                Section::make('Payment Information')
                    ->schema([
                        Forms\Components\Placeholder::make('payment_reference')
                            ->label('Payment Reference')
                            ->content(fn ($record) => $record->payment?->payment_reference ?? 'No payment recorded'),
                        Forms\Components\Placeholder::make('payment_amount')
                            ->label('Payment Amount')
                            ->content(fn ($record) => $record->payment ? '$' . number_format($record->payment->amount, 2) : 'No payment recorded'),
                        Forms\Components\Placeholder::make('payment_method')
                            ->label('Payment Method')
                            ->content(fn ($record) => $record->payment?->payment_method?->getLabel() ?? 'No payment recorded'),
                        Forms\Components\Placeholder::make('payment_status')
                            ->label('Payment Status')
                            ->content(fn ($record) => $record->payment?->status?->getLabel() ?? 'No payment recorded'),
                        Forms\Components\Placeholder::make('paid_at')
                            ->label('Paid At')
                            ->content(function ($record) {
                                if (!$record->payment || !$record->payment->paid_at) {
                                    return 'No payment recorded';
                                }
                                
                                $paidAt = $record->payment->paid_at;
                                if (is_numeric($paidAt)) {
                                    return \Carbon\Carbon::createFromTimestamp($paidAt)->format('M d, Y H:i');
                                }
                                
                                return $paidAt->format('M d, Y H:i');
                            }),
                        Forms\Components\Placeholder::make('payment_notes')
                            ->label('Payment Notes')
                            ->content(fn ($record) => $record->payment?->notes ?? 'No notes'),
                    ])
                    ->columns(2)
                    ->collapsible()
                    ->collapsed(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('chalet.name')
                    ->label('Chalet')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Customer')
                    ->sortable(),
                Tables\Columns\TextColumn::make('booking_reference')
                    ->label('Reference')
                    ->searchable(),
                Tables\Columns\TextColumn::make('start_date')
                    ->label('Start Date')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('end_date')
                    ->label('End Date')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->sortable(),
                Tables\Columns\TextColumn::make('payment_status')
                    ->badge()
                    ->sortable(),
                Tables\Columns\TextColumn::make('total_amount')
                    ->label('Total')
                    ->numeric()
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('addPayment')
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
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBookings::route('/'),
            'create' => Pages\CreateBooking::route('/create'),
            'view' => Pages\ViewBooking::route('/{record}'),
            'edit' => Pages\EditBooking::route('/{record}/edit'),
        ];
    }
}
