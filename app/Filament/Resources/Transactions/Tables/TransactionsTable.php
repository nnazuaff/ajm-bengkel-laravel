<?php

namespace App\Filament\Resources\Transactions\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class TransactionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('work_order_id')
                    ->translateLabel()
                    ->numeric()
                    ->sortable(),
                TextColumn::make('type')
                    ->translateLabel()
                    ->formatStateUsing(fn ($state): string => __($state === 'income' ? 'Income' : 'Expense'))
                    ->badge(),
                TextColumn::make('amount')
                    ->translateLabel()
                    ->numeric()
                    ->sortable(),
                TextColumn::make('transaction_date')
                    ->translateLabel()
                    ->date()
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
