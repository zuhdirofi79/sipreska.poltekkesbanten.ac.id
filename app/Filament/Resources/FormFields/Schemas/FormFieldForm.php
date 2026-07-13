<?php

namespace App\Filament\Resources\FormFields\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class FormFieldForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('event_form_id')
                    ->required()
                    ->numeric(),
                TextInput::make('label')
                    ->required(),
                TextInput::make('name')
                    ->required(),
                TextInput::make('type')
                    ->required()
                    ->default('text'),
                Textarea::make('options')
                    ->default(null)
                    ->columnSpanFull(),
                Toggle::make('is_required')
                    ->required(),
                TextInput::make('sort_order')
                    ->required()
                    ->numeric()
                    ->default(0),
            ]);
    }
}
