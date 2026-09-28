<?php

namespace App\Filament\Resources\Spareparts\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class SparepartForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->translateLabel()
                    ->required(),
                TextInput::make('sku')
                    ->label('SKU')
                    ->translateLabel()
                    ->required(),
                TextInput::make('category')
                    ->translateLabel()
                    ->required(),
                TextInput::make('stock')
                    ->translateLabel()
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('min_stock')
                    ->translateLabel()
                    ->required()
                    ->numeric()
                    ->default(5),
                TextInput::make('unit_price')
                    ->translateLabel()
                    ->required()
                    ->numeric()
                    ->prefix('$'),
            ]);
    }
}
