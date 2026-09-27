<?php

namespace App\Filament\Resources\Transactions\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class TransactionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('work_order_id')
                    ->numeric(),
                Select::make('type')
                    ->options(['income' => 'Income', 'expense' => 'Expense'])
                    ->required(),
                TextInput::make('amount')
                    ->required()
                    ->numeric(),
                Textarea::make('description')
                    ->required()
                    ->columnSpanFull(),
                DatePicker::make('transaction_date')
                    ->required(),
            ]);
    }
}
