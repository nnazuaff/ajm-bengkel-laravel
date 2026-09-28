<?php

namespace App\Filament\Resources\AuditLogs\Tables;

use App\Services\AuditRollbackService;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AuditLogsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user_id')
                    ->translateLabel()
                    ->numeric()
                    ->sortable(),
                TextColumn::make('table_name')
                    ->translateLabel()
                    ->searchable(),
                TextColumn::make('row_id')
                    ->translateLabel()
                    ->numeric()
                    ->sortable(),
                TextColumn::make('action')
                    ->translateLabel()
                    ->formatStateUsing(fn ($state): string => __(ucfirst((string) $state)))
                    ->badge(),
                TextColumn::make('created_at')
                    ->translateLabel()
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                Action::make('rollback')
                    ->label(fn (): string => __('Rollback'))
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalDescription(fn (): string => __('This will restore the data to its previous state. This action cannot be undone.'))
                    ->visible(fn ($record) => app(AuditRollbackService::class)->canRollback($record))
                    ->action(function ($record) {
                        $service = app(AuditRollbackService::class);

                        if ($service->rollback($record)) {
                            Notification::make()
                                ->success()
                                ->title(__('Rollback successful'))
                                ->body(__('The data was restored to its previous state.'))
                                ->send();
                        } else {
                            Notification::make()
                                ->danger()
                                ->title(__('Rollback failed'))
                                ->body(__('An error occurred while rolling back the data.'))
                                ->send();
                        }
                    }),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
