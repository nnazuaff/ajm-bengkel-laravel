<?php

namespace App\Filament\Resources\WorkOrders\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class WorkOrdersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('vehicle.license_plate')
                    ->label('License plate')
                    ->translateLabel()
                    ->searchable()
                    ->sortable(),
                TextColumn::make('mechanic.name')
                    ->label('Mechanic')
                    ->translateLabel()
                    ->searchable()
                    ->sortable(),
                TextColumn::make('status')
                    ->translateLabel()
                    ->formatStateUsing(fn ($state): string => __(
                        $state === 'in_progress' ? 'In progress' : ucfirst((string) $state),
                    ))
                    ->badge(),
                TextColumn::make('total_cost')
                    ->translateLabel()
                    ->money()
                    ->sortable(),
                TextColumn::make('started_at')
                    ->translateLabel()
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('completed_at')
                    ->translateLabel()
                    ->dateTime()
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
