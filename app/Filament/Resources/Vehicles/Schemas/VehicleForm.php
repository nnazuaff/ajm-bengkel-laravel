<?php

namespace App\Filament\Resources\Vehicles\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class VehicleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('customer_id')
                    ->translateLabel()
                    ->required()
                    ->numeric(),
                TextInput::make('license_plate')
                    ->translateLabel()
                    ->required(),
                TextInput::make('brand')
                    ->translateLabel()
                    ->required(),
                TextInput::make('model')
                    ->translateLabel()
                    ->required(),
                TextInput::make('year')
                    ->translateLabel()
                    ->required(),
            ]);
    }
}
