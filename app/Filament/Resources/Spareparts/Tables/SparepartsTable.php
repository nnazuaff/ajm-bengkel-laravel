<?php

namespace App\Filament\Resources\Spareparts\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SparepartsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->translateLabel()
                    ->searchable(),
                TextColumn::make('sku')
                    ->label('SKU')
                    ->translateLabel()
                    ->searchable(),
                TextColumn::make('category')
                    ->translateLabel()
                    ->searchable(),
                TextColumn::make('stock')
                    ->translateLabel()
                    ->numeric()
                    ->sortable(),
                TextColumn::make('min_stock')
                    ->translateLabel()
                    ->numeric()
                    ->sortable(),
                TextColumn::make('unit_price')
                    ->translateLabel()
                    ->money()
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
