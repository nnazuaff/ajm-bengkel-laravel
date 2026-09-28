<?php

namespace App\Filament\Resources\Spareparts;

use App\Filament\Resources\Concerns\HasDynamicNavigation;
use App\Filament\Resources\Spareparts\Pages\CreateSparepart;
use App\Filament\Resources\Spareparts\Pages\EditSparepart;
use App\Filament\Resources\Spareparts\Pages\ListSpareparts;
use App\Filament\Resources\Spareparts\Schemas\SparepartForm;
use App\Filament\Resources\Spareparts\Tables\SparepartsTable;
use App\Models\Sparepart;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class SparepartResource extends Resource
{
    use HasDynamicNavigation;

    protected static ?string $model = Sparepart::class;

    protected static string|UnitEnum|null $navigationGroup = 'master_data';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCube;

    protected static ?int $navigationSort = 30;

    public static function getNavigationLabel(): string
    {
        return __('Spareparts');
    }

    public static function getModelLabel(): string
    {
        return __('Sparepart');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Spareparts');
    }

    public static function form(Schema $schema): Schema
    {
        return SparepartForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SparepartsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSpareparts::route('/'),
            'create' => CreateSparepart::route('/create'),
            'edit' => EditSparepart::route('/{record}/edit'),
        ];
    }
}
