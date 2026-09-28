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
                    ->translateLabel()
                    ->required()
                    ->numeric(),
                TextInput::make('table_name')
                    ->translateLabel()
                    ->required(),
                TextInput::make('row_id')
                    ->translateLabel()
                    ->required()
                    ->numeric(),
                Select::make('action')
                    ->translateLabel()
                    ->options(fn (): array => [
                        'create' => __('Create'),
                        'update' => __('Update'),
                        'delete' => __('Delete'),
                    ])
                    ->required(),
                TextInput::make('old_values')
                    ->translateLabel(),
                TextInput::make('new_values')
                    ->translateLabel(),
            ]);
    }
}
