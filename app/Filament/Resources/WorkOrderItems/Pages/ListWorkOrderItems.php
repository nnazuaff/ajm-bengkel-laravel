<?php

namespace App\Filament\Resources\WorkOrderItems\Pages;

use App\Filament\Resources\WorkOrderItems\WorkOrderItemResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListWorkOrderItems extends ListRecords
{
    protected static string $resource = WorkOrderItemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
