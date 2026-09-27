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
                    ->required(),
                TextInput::make('sku')
                    ->label('SKU')
                    ->required(),
                TextInput::make('category')
                    ->required(),
                TextInput::make('stock')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('min_stock')
                    ->required()
                    ->numeric()
                    ->default(5),
                TextInput::make('unit_price')
                    ->required()
                    ->numeric()
                    ->prefix('$'),
            ]);
    }
}
