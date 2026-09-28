<?php

namespace App\Filament\Resources\AuditLogs;

use App\Filament\Resources\AuditLogs\Pages\CreateAuditLog;
use App\Filament\Resources\AuditLogs\Pages\EditAuditLog;
use App\Filament\Resources\AuditLogs\Pages\ListAuditLogs;
use App\Filament\Resources\AuditLogs\Schemas\AuditLogForm;
use App\Filament\Resources\AuditLogs\Tables\AuditLogsTable;
use App\Filament\Resources\Concerns\HasDynamicNavigation;
use App\Models\AuditLog;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class AuditLogResource extends Resource
{
    use HasDynamicNavigation;

    protected static ?string $model = AuditLog::class;

    protected static string|UnitEnum|null $navigationGroup = 'system';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;

    protected static ?int $navigationSort = 10;

    public static function getNavigationLabel(): string
    {
        return __('Audit Logs');
    }

    public static function getModelLabel(): string
    {
        return __('Audit Log');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Audit Logs');
    }

    public static function form(Schema $schema): Schema
    {
        return AuditLogForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AuditLogsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAuditLogs::route('/'),
            'create' => CreateAuditLog::route('/create'),
            'edit' => EditAuditLog::route('/{record}/edit'),
        ];
    }
}
