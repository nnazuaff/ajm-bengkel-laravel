<?php

namespace App\Filament\Resources\ServicePhotos\Pages;

use App\Filament\Resources\ServicePhotos\ServicePhotoResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditServicePhoto extends EditRecord
{
    protected static string $resource = ServicePhotoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
