<?php

namespace App\Filament\Resources\ServicePhotos;

use App\Filament\Resources\ServicePhotos\Pages\CreateServicePhoto;
use App\Filament\Resources\ServicePhotos\Pages\EditServicePhoto;
use App\Filament\Resources\ServicePhotos\Pages\ListServicePhotos;
use App\Filament\Resources\ServicePhotos\Schemas\ServicePhotoForm;
use App\Filament\Resources\ServicePhotos\Tables\ServicePhotosTable;
use App\Models\ServicePhoto;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ServicePhotoResource extends Resource
{
    protected static ?string $model = ServicePhoto::class;

    protected static ?string $navigationIcon = 'heroicon-o-camera';






    public static function form(Schema $schema): Schema
    {
        return ServicePhotoForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ServicePhotosTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListServicePhotos::route('/'),
            'create' => CreateServicePhoto::route('/create'),
            'edit' => EditServicePhoto::route('/{record}/edit'),
        ];
    }
}
