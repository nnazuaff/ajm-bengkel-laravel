<?php

namespace App\Filament\Resources\ServicePhotos\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class ServicePhotoForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('work_order_item_id')
                    ->required()
                    ->numeric(),
                TextInput::make('photo_path')
                    ->required(),
                Textarea::make('description')
                    ->columnSpanFull(),
                DateTimePicker::make('uploaded_at')
                    ->required(),
            ]);
    }
}
