<?php

namespace App\Filament\Resources\WorkOrderItems\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class WorkOrderItemForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('work_order_id')
                    ->required()
                    ->numeric(),
                TextInput::make('sparepart_id')
                    ->numeric(),
                TextInput::make('service_name')
                    ->required(),
                TextInput::make('quantity')
                    ->required()
                    ->numeric()
                    ->default(1),
                TextInput::make('unit_price')
                    ->required()
                    ->numeric()
                    ->prefix('$'),
                TextInput::make('subtotal')
                    ->required()
                    ->numeric(),
            ]);
    }
}
