<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Enums\ChaletStatus;
use App\Filament\Resources\ChaletResource\Pages;
use App\Filament\Resources\ChaletResource\RelationManagers;
use App\Models\Chalet;
use Cheesegrits\FilamentGoogleMaps\Fields\Map;
use Filament\Forms;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Form;
use Filament\Forms\Set;
use Filament\Resources\RelationManagers\RelationGroup;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\SpatieMediaLibraryImageColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use Ysfkaya\FilamentPhoneInput\Forms\PhoneInput;
use Ysfkaya\FilamentPhoneInput\PhoneInputNumberType;
use Ysfkaya\FilamentPhoneInput\Tables\PhoneColumn;

final class ChaletResource extends Resource
{
    protected static ?string $model = Chalet::class;

    protected static ?string $navigationIcon = 'heroicon-o-home-modern';

    protected static ?string $navigationGroup = 'Chalet Management';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Tabs::make('Chalet Tabs')
                ->tabs([
                    Forms\Components\Tabs\Tab::make('Main')
                        ->schema([
                            Forms\Components\Section::make('Chalet Information')
                                ->schema([
                                    Forms\Components\TextInput::make('name')->required()->maxLength(255)
                                        ->live(onBlur: true)
                                        ->afterStateUpdated(fn (Set $set, ?string $state) => $set('slug', Str::slug($state))),
                                    Forms\Components\TextInput::make('slug')->required()->maxLength(255)->unique(ignoreRecord: true),
                                    Forms\Components\Select::make('owner_id')->relationship('owner', 'name')->searchable()->required(),
                                    Forms\Components\Select::make('status')->options(ChaletStatus::class)->native(false)->required(),
                                    Forms\Components\RichEditor::make('description')->columnSpanFull(),
                                    Forms\Components\ToggleButtons::make('is_featured')->boolean()->inline()->required(),
                                    Forms\Components\DateTimePicker::make('featured_until'),
                                ])->columns(2),

                            Forms\Components\Section::make('Details')
                                ->schema([
                                    Forms\Components\TextInput::make('max_adults')->numeric(),
                                    Forms\Components\TextInput::make('max_children')->numeric(),
                                    Forms\Components\TextInput::make('bedrooms_count')->numeric(),
                                    Forms\Components\TextInput::make('bathrooms_count')->numeric(),
                                    Forms\Components\Select::make('weekend_days')
                                        ->label('Weekend Days')
                                        ->multiple()
                                        ->options([
                                            0 => 'Sunday',
                                            1 => 'Monday',
                                            2 => 'Tuesday',
                                            3 => 'Wednesday',
                                            4 => 'Thursday',
                                            5 => 'Friday',
                                            6 => 'Saturday',
                                        ])
                                        ->required()
                                        ->helperText('Select which days are considered weekend for this chalet.'),
                                ])->columns(4),

                            Forms\Components\Section::make('Instructions & Policies')
                                ->schema([
                                    Forms\Components\Textarea::make('check_in_instructions')->columnSpanFull(),
                                    Forms\Components\Textarea::make('house_rules')->columnSpanFull(),
                                    Forms\Components\Textarea::make('cancellation_policy')->columnSpanFull(),
                                ]),
                        ]),

                    Forms\Components\Tabs\Tab::make('SEO & Social')
                        ->schema([
                            Forms\Components\Section::make('SEO')
                                ->schema([
                                    Forms\Components\TextInput::make('meta_title')->maxLength(255),
                                    Forms\Components\Textarea::make('meta_description'),
                                ]),
                            Forms\Components\Section::make('Social Media')
                                ->schema([
                                    Forms\Components\TextInput::make('facebook_url')->url()->maxLength(500),
                                    Forms\Components\TextInput::make('instagram_url')->url()->maxLength(500),
                                    Forms\Components\TextInput::make('website_url')->url()->maxLength(500),
                                    PhoneInput::make('whatsapp_number'),
                                ]),
                        ]),

                    Forms\Components\Tabs\Tab::make('Financials & Insights')
                        ->schema([
                            Forms\Components\Section::make('Bank Details')
                                ->schema([
                                    Forms\Components\TextInput::make('bank_name')->maxLength(100),
                                    Forms\Components\TextInput::make('account_holder_name')->maxLength(100),
                                    Forms\Components\TextInput::make('account_number')->maxLength(50),
                                    Forms\Components\TextInput::make('iban')->maxLength(50),
                                ]),
                            Forms\Components\Section::make('Earnings')
                                ->schema([
                                    Forms\Components\TextInput::make('total_earnings')->numeric(),
                                    Forms\Components\TextInput::make('pending_earnings')->numeric(),
                                    Forms\Components\TextInput::make('total_withdrawn')->numeric(),
                                ])->columns(3)->visibleOn('view'),
                            Forms\Components\Section::make('Ratings')
                                ->schema([
                                    Forms\Components\TextInput::make('average_rating')->numeric(),
                                    Forms\Components\TextInput::make('total_reviews')->numeric(),
                                ])->columns(2)->visibleOn('view'),
                        ]),

                    Forms\Components\Tabs\Tab::make('Media')
                        ->schema([
                            SpatieMediaLibraryFileUpload::make('featured_image')
                                ->collection('featured_image')
                                ->image()
                                ->optimize('webp') // Using WebP for better compression
                                ->resize(80) // Resize to 80% of original
                                ->required(),
                            SpatieMediaLibraryFileUpload::make('media')
                                ->collection('gallery')
                                ->multiple()
                                ->image()
                                ->optimize('webp')
                                ->resize(80)
                                ->reorderable(),
                        ]),
                    Forms\Components\Tabs\Tab::make('Location')
                        ->schema([
                            Forms\Components\TextInput::make('address'),
                            Forms\Components\TextInput::make('city')->maxLength(100),
                            Map::make('location')
                                ->geolocate()
                                ->clickable(true)
                                ->autocomplete(
                                    fieldName: 'address',
                                    types: ['establishment'],
                                    placeField: 'name',
                                    countries: ['LB'],
                                )
                                ->reverseGeocode([
                                    'address' => '%n %S, %L, %A1, %z',
                                    'city' => '%L',
                                ])
                                ->columnSpanFull(),
                        ])->columns(2),
                ])->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                SpatieMediaLibraryImageColumn::make('image')
                    ->collection('featured_image')
                    ->conversion('thumb'),
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('owner.name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('city')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->searchable()
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_featured')
                    ->boolean()
                    ->sortable(),
                Tables\Columns\TextColumn::make('average_rating')
                    ->numeric()
                    ->sortable(),
                PhoneColumn::make('whatsapp_number')->displayFormat(PhoneInputNumberType::INTERNATIONAL),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
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
            RelationGroup::make('Features', [
                RelationManagers\AmenitiesRelationManager::class,
                RelationManagers\FacilitiesRelationManager::class,
            ]),
            RelationGroup::make('Pricing & Availability', [
                RelationManagers\TimeSlotsRelationManager::class,
                RelationManagers\CustomPricingRelationManager::class,
                RelationManagers\BlockedDatesRelationManager::class,
            ]),
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListChalets::route('/'),
            'create' => Pages\CreateChalet::route('/create'),
            'view' => Pages\ViewChalet::route('/{record}'),
            'edit' => Pages\EditChalet::route('/{record}/edit'),
        ];
    }
}
