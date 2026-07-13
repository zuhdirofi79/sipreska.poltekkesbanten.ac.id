<?php

namespace App\Filament\Resources\EventForms\Pages;

use App\Filament\Resources\EventForms\EventFormResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditEventForm extends EditRecord
{
    protected static string $resource = EventFormResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
