<?php

namespace App\Filament\Resources\ServicePhotos\Schemas;

use App\Models\WorkOrderItem;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class ServicePhotoForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('work_order_item_id')
                    ->translateLabel()
                    ->relationship('workOrderItem', 'service_name')
                    ->getOptionLabelFromRecordUsing(
                        fn (WorkOrderItem $record): string => "#{$record->id} - {$record->service_name}",
                    )
                    ->searchable()
                    ->preload()
                    ->required(),
                FileUpload::make('photo_path')
                    ->translateLabel()
                    ->image()
                    ->disk('public')
                    ->directory('service-photos')
                    ->visibility('public')
                    ->maxSize(10240)
                    ->openable()
                    ->downloadable()
                    ->required(),
                Textarea::make('description')
                    ->translateLabel()
                    ->columnSpanFull(),
                DateTimePicker::make('uploaded_at')
                    ->translateLabel()
                    ->default(now())
                    ->required(),
            ]);
    }
}
