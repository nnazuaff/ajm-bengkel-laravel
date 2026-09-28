<?php

namespace App\Filament\Resources\WorkOrderItems\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class WorkOrderItemsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('workOrder.vehicle.license_plate')
                    ->label('Work Order')
                    ->translateLabel()
                    ->searchable()
                    ->sortable(),
                TextColumn::make('sparepart.name')
                    ->label('Sparepart')
                    ->translateLabel()
                    ->searchable()
                    ->sortable(),
                TextColumn::make('service_name')
                    ->translateLabel()
                    ->searchable(),
                TextColumn::make('quantity')
                    ->translateLabel()
                    ->numeric()
                    ->sortable(),
                TextColumn::make('unit_price')
                    ->translateLabel()
                    ->money()
                    ->sortable(),
                TextColumn::make('subtotal')
                    ->translateLabel()
                    ->numeric()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->translateLabel()
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->translateLabel()
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
