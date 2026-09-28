<?php

namespace App\Filament\Resources\Transactions\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class TransactionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('work_order_id')
                    ->translateLabel()
                    ->numeric(),
                Select::make('type')
                    ->translateLabel()
                    ->options(fn (): array => [
                        'income' => __('Income'),
                        'expense' => __('Expense'),
                    ])
                    ->required(),
                TextInput::make('amount')
                    ->translateLabel()
                    ->required()
                    ->numeric(),
                Textarea::make('description')
                    ->translateLabel()
                    ->required()
                    ->columnSpanFull(),
                DatePicker::make('transaction_date')
                    ->translateLabel()
                    ->required(),
            ]);
    }
}
