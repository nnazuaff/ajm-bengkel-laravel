<?php

namespace App\Filament\Resources\Customers\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class CustomerForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->translateLabel()
                    ->required(),
                TextInput::make('phone')
                    ->translateLabel()
                    ->tel()
                    ->required(),
                Textarea::make('address')
                    ->translateLabel()
                    ->columnSpanFull(),
            ]);
    }
}
