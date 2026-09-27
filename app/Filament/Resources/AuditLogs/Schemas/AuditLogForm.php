<?php

namespace App\Filament\Resources\AuditLogs\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class AuditLogForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('user_id')
                    ->required()
                    ->numeric(),
                TextInput::make('table_name')
                    ->required(),
                TextInput::make('row_id')
                    ->required()
                    ->numeric(),
                Select::make('action')
                    ->options(['create' => 'Create', 'update' => 'Update', 'delete' => 'Delete'])
                    ->required(),
                TextInput::make('old_values'),
                TextInput::make('new_values'),
            ]);
    }
}
