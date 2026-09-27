<?php

namespace App\Filament\Resources\WorkOrderItems\Pages;

use App\Filament\Resources\WorkOrderItems\WorkOrderItemResource;
use Filament\Resources\Pages\CreateRecord;

class CreateWorkOrderItem extends CreateRecord
{
    protected static string $resource = WorkOrderItemResource::class;
}
