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
                    ->numeric()
                    ->sortable(),
                TextColumn::make('table_name')
                    ->searchable(),
                TextColumn::make('row_id')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('action')
                    ->badge(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                Action::make('rollback')
                    ->label('Rollback')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalDescription('Akan mengembalikan data ke kondisi sebelumnya. Aksi ini tidak bisa di-undo.')
                    ->visible(fn ($record) => app(AuditRollbackService::class)->canRollback($record))
                    ->action(function ($record) {
                        $service = app(AuditRollbackService::class);
                        
                        if ($service->rollback($record)) {
                            Notification::make()
                                ->success()
                                ->title('Rollback berhasil')
                                ->body('Data berhasil dikembalikan ke kondisi sebelumnya.')
                                ->send();
                        } else {
                            Notification::make()
                                ->danger()
                                ->title('Rollback gagal')
                                ->body('Terjadi kesalahan saat rollback.')
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
