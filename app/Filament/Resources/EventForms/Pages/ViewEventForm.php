<?php

namespace App\Filament\Resources\EventForms\Pages;

use App\Filament\Resources\EventForms\EventFormResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewEventForm extends ViewRecord
{
    protected static string $resource = EventFormResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
