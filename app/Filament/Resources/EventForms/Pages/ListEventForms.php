<?php

namespace App\Filament\Resources\EventForms\Pages;

use App\Filament\Resources\EventForms\EventFormResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListEventForms extends ListRecords
{
    protected static string $resource = EventFormResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
