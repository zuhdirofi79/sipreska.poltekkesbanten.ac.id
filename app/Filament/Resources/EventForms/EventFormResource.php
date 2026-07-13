<?php

namespace App\Filament\Resources\EventForms;

use App\Filament\Resources\EventForms\Pages\CreateEventForm;
use App\Filament\Resources\EventForms\Pages\EditEventForm;
use App\Filament\Resources\EventForms\Pages\ListEventForms;
use App\Filament\Resources\EventForms\Pages\ViewEventForm;
use App\Filament\Resources\EventForms\Schemas\EventFormForm;
use App\Filament\Resources\EventForms\Schemas\EventFormInfolist;
use App\Filament\Resources\EventForms\Tables\EventFormsTable;
use App\Models\EventForm;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class EventFormResource extends Resource
{
    protected static ?string $model = EventForm::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'title';

    public static function form(Schema $schema): Schema
    {
        return EventFormForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return EventFormInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return EventFormsTable::configure($table);
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
            'index' => ListEventForms::route('/'),
            'create' => CreateEventForm::route('/create'),
            'view' => ViewEventForm::route('/{record}'),
            'edit' => EditEventForm::route('/{record}/edit'),
        ];
    }
}
