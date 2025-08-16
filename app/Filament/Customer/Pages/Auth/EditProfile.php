<?php

namespace App\Filament\Customer\Pages\Auth;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Pages\Auth\EditProfile as BaseEditProfile;

class EditProfile extends BaseEditProfile
{
    public function form(Form $form): Form
    {
        return $form
            ->schema([
                $this->getNameFormComponent(),
                $this->getEmailFormComponent(),
                TextInput::make('phone')
                    ->label('Phone Number')
                    ->tel()
                    ->required()
                    ->maxLength(20)
                    ->placeholder('e.g., +966 50 123 4567')
                    ->unique(ignoreRecord: true)
                    ->regex('/^[\+]?[0-9\s\-\(\)]+$/')
                    ->validationMessages([
                        'regex' => 'Please enter a valid phone number format.',
                        'unique' => 'This phone number is already registered.',
                    ]),
                $this->getPasswordFormComponent(),
                $this->getPasswordConfirmationFormComponent(),
            ]);
    }
}
