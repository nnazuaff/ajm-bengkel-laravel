<?php

namespace App\Filament\Resources\WorkOrderItems\Pages;

use App\Filament\Resources\WorkOrderItems\WorkOrderItemResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditWorkOrderItem extends EditRecord
{
    protected static string $resource = WorkOrderItemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
