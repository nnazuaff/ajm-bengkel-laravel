<?php

namespace App\Filament\Resources\WorkOrderItems\Schemas;

use App\Models\WorkOrder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class WorkOrderItemForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('work_order_id')
                    ->translateLabel()
                    ->relationship('workOrder', 'id')
                    ->getOptionLabelFromRecordUsing(
                        fn (WorkOrder $record): string => $record->vehicle
                            ? "#{$record->id} - {$record->vehicle->license_plate}"
                            : "#{$record->id}",
                    )
                    ->searchable()
                    ->preload()
                    ->required(),
                Select::make('sparepart_id')
                    ->translateLabel()
                    ->relationship('sparepart', 'name')
                    ->searchable()
                    ->preload(),
                TextInput::make('service_name')
                    ->translateLabel()
                    ->required(),
                TextInput::make('quantity')
                    ->translateLabel()
                    ->required()
                    ->numeric()
                    ->default(1),
                TextInput::make('unit_price')
                    ->translateLabel()
                    ->required()
                    ->numeric()
                    ->prefix('$'),
                TextInput::make('subtotal')
                    ->translateLabel()
                    ->required()
                    ->numeric(),
            ]);
    }
}
