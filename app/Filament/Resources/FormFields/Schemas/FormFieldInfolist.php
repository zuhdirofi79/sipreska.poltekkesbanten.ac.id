<?php

namespace App\Filament\Resources\FormFields\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class FormFieldInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('event_form_id')
                    ->numeric(),
                TextEntry::make('label'),
                TextEntry::make('name'),
                TextEntry::make('type'),
                TextEntry::make('options')
                    ->placeholder('-')
                    ->columnSpanFull(),
                IconEntry::make('is_required')
                    ->boolean(),
                TextEntry::make('sort_order')
                    ->numeric(),
                TextEntry::make('created_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('updated_at')
                    ->dateTime()
                    ->placeholder('-'),
            ]);
    }
}
