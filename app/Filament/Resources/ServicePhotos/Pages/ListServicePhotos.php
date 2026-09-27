<?php

namespace App\Filament\Resources\ServicePhotos\Pages;

use App\Filament\Resources\ServicePhotos\ServicePhotoResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListServicePhotos extends ListRecords
{
    protected static string $resource = ServicePhotoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
