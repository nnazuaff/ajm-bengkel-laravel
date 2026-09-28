<?php

namespace App\Filament\Resources\WorkOrderItems;

use App\Filament\Resources\Concerns\HasDynamicNavigation;
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
use UnitEnum;

class WorkOrderItemResource extends Resource
{
    use HasDynamicNavigation;

    protected static ?string $model = WorkOrderItem::class;

    protected static string|UnitEnum|null $navigationGroup = 'operations';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedListBullet;

    protected static ?int $navigationSort = 20;

    public static function getNavigationLabel(): string
    {
        return __('Work Order Items');
    }

    public static function getModelLabel(): string
    {
        return __('Work Order Item');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Work Order Items');
    }

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
        return [];
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
