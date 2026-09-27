<?php

namespace App\Filament\Resources\WorkOrderItems;

use App\Filament\Resources\WorkOrderItems\Pages\CreateWorkOrderItem;
use App\Filament\Resources\WorkOrderItems\Pages\EditWorkOrderItem;
use App\Filament\Resources\WorkOrderItems\Pages\ListWorkOrderItems;
use App\Filament\Resources\WorkOrderItems\Schemas\WorkOrderItemForm;
use App\Filament\Resources\WorkOrderItems\Tables\WorkOrderItemsTable;
use App\Models\WorkOrderItem;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class WorkOrderItemResource extends Resource
{
    protected static ?string $model = WorkOrderItem::class;

    protected static ?string $navigationIcon = 'heroicon-o-list-bullet';

    protected static ?string $navigationLabel = 'Work Order Items';

    protected static ?string $navigationGroup = 'Operations';

    protected static ?int $navigationSort = 2;



    public static function form(Schema $schema): Schema
    {
        return WorkOrderItemForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return WorkOrderItemsTable::configure($table);
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
            'index' => ListWorkOrderItems::route('/'),
            'create' => CreateWorkOrderItem::route('/create'),
            'edit' => EditWorkOrderItem::route('/{record}/edit'),
        ];
    }
}
