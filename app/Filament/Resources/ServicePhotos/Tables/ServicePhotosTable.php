<?php

namespace App\Filament\Resources\ServicePhotos\Tables;

use App\Models\ServicePhoto;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ServicePhotosTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('workOrderItem.service_name')
                    ->label('Service name')
                    ->translateLabel()
                    ->searchable(),
                ImageColumn::make('photo_path')
                    ->translateLabel()
                    ->disk('public')
                    ->square()
                    ->imageSize(64)
                    ->url(
                        fn (ServicePhoto $record): ?string => filled($record->photo_path)
                            ? asset('storage/'.ltrim($record->photo_path, '/'))
                            : null,
                    )
                    ->openUrlInNewTab(),
                TextColumn::make('description')
                    ->translateLabel()
                    ->limit(60),
                TextColumn::make('uploaded_at')
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
