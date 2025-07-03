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
use Filament\Infolists\Infolist;
use Filament\Infolists\Components\Grid;
use Filament\Infolists\Components\Tabs;
use Filament\Infolists\Components\TextEntry;

final class BookingResource extends Resource
{
    protected static ?string $model = Booking::class;

    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';

    protected static ?string $navigationGroup = 'Chalet Management';

    protected static ?string $recordTitleAttribute = 'booking_reference';

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
                        Forms\Components\TextInput::make('discount_amount')
                            ->numeric()
                            ->default(0),
                        Forms\Components\TextInput::make('discount_percentage')
                            ->numeric()
                            ->default(0),
                        Forms\Components\TextInput::make('discount_reason'),
                        Forms\Components\TextInput::make('total_amount')
                            ->required()
                            ->numeric(),
                        Forms\Components\TextInput::make('owner_earning')
                            ->numeric()
                            ->default(0),
                        Forms\Components\TextInput::make('platform_earning')
                            ->numeric()
                            ->default(0),
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
                        Forms\Components\Placeholder::make('payment.payment_reference')
                            ->label('Payment Reference')
                            ->content(fn ($record) => $record->payment?->payment_reference ?? 'No payment recorded'),
                        Forms\Components\Placeholder::make('payment.amount')
                            ->label('Payment Amount')
                            ->content(fn ($record) => $record->payment ? '$' . number_format($record->payment->amount, 2) : 'No payment recorded'),
                        Forms\Components\Placeholder::make('payment.payment_method')
                            ->label('Payment Method')
                            ->content(fn ($record) => $record->payment?->payment_method?->getLabel() ?? 'No payment recorded'),
                        Forms\Components\Placeholder::make('payment.status')
                            ->label('Payment Status')
                            ->content(fn ($record) => $record->payment?->status?->getLabel() ?? 'No payment recorded'),
                        Forms\Components\Placeholder::make('payment.paid_at')
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
                        Forms\Components\Placeholder::make('payment.notes')
                            ->label('Payment Notes')
                            ->content(fn ($record) => $record->payment?->notes ?? 'No notes'),
                    ])
                    ->columns(2)
                    ->collapsible()
                    ->collapsed(),
            ]);
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
                                            TextEntry::make('user.phone')->label('Customer Phone'),
                                        ]),
                                    ]),
                                Section::make('Owner Information')
                                    ->schema([
                                        Grid::make(2)->schema([
                                            TextEntry::make('chalet.owner.name')->label('Owner Name'),
                                            TextEntry::make('chalet.owner.email')->label('Owner Email'),
                                            TextEntry::make('chalet.owner.phone')->label('Owner Phone'),
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
                                            TextEntry::make('adults_count')->label('Adults'),
                                            TextEntry::make('children_count')->label('Children'),
                                            TextEntry::make('total_guests')->label('Total Guests'),
                                            TextEntry::make('special_requests')->label('Special Requests')->columnSpanFull(),
                                            TextEntry::make('internal_notes')->label('Internal Notes')->columnSpanFull(),
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
                                    ]),
                                Section::make('Cancellation')
                                    ->schema([
                                        Grid::make(2)->schema([
                                            TextEntry::make('cancelled_at')->dateTime()->label('Cancelled At'),
                                            TextEntry::make('cancellation_reason')->label('Cancellation Reason'),
                                            TextEntry::make('auto_completed_at')->dateTime()->label('Auto Completed At'),
                                        ]),
                                    ])->collapsible(),
                                Section::make('Timestamps')
                                    ->schema([
                                        Grid::make(2)->schema([
                                            TextEntry::make('created_at')->dateTime()->label('Created At'),
                                            TextEntry::make('updated_at')->dateTime()->label('Updated At'),
                                        ]),
                                    ])->collapsible(),
                            ]),
                    ])->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('booking_reference')->label('Reference')->searchable(),
                Tables\Columns\TextColumn::make('chalet.name')->label('Chalet')->sortable(),
                Tables\Columns\TextColumn::make('user.name')->label('Customer')->sortable(),
                Tables\Columns\TextColumn::make('chalet.owner.name')->label('Owner')->sortable(),
                Tables\Columns\TextColumn::make('start_date')->label('Check-in')->dateTime()->sortable(),
                Tables\Columns\TextColumn::make('end_date')->label('Check-out')->dateTime()->sortable(),
                Tables\Columns\TextColumn::make('total_amount')->label('Total')->money('USD')->sortable(),
                Tables\Columns\TextColumn::make('payment_status')->badge()->sortable(),
                Tables\Columns\TextColumn::make('status')->badge()->sortable(),
                Tables\Columns\TextColumn::make('owner_earning')->label('Owner Earning')->money('USD')->sortable(),
                Tables\Columns\TextColumn::make('platform_earning')->label('Platform Earning')->money('USD')->sortable(),
                Tables\Columns\TextColumn::make('remaining_payment')->label('Remaining')->money('USD')->sortable(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\DeleteAction::make(),
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
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBookings::route('/'),
            'view' => Pages\ViewBooking::route('/{record}'),
        ];
    }
}
