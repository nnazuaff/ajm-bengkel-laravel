<?php

namespace App\Filament\Resources\WorkOrders\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class WorkOrderForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('vehicle_id')
                    ->translateLabel()
                    ->relationship('vehicle', 'license_plate')
                    ->searchable()
                    ->preload()
                    ->required(),
                Select::make('mechanic_id')
                    ->translateLabel()
                    ->relationship(
                        'mechanic',
                        'name',
                        modifyQueryUsing: fn ($query) => $query->whereIn('role', ['admin', 'mechanic']),
                    )
                    ->searchable()
                    ->preload()
                    ->required(),
                Select::make('status')
                    ->translateLabel()
                    ->options(fn (): array => [
                        'pending' => __('Pending'),
                        'in_progress' => __('In progress'),
                        'completed' => __('Completed'),
                        'paid' => __('Paid'),
                    ])
                    ->default('pending')
                    ->required(),
                Textarea::make('description')
                    ->translateLabel()
                    ->required()
                    ->columnSpanFull(),
                TextInput::make('total_cost')
                    ->translateLabel()
                    ->required()
                    ->numeric()
                    ->default(0.0)
                    ->prefix('$'),
                DateTimePicker::make('started_at')
                    ->translateLabel(),
                DateTimePicker::make('completed_at')
                    ->translateLabel(),
            ]);
    }
}
