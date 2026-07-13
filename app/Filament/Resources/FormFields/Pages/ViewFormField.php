<?php

namespace App\Filament\Resources\FormFields\Pages;

use App\Filament\Resources\FormFields\FormFieldResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewFormField extends ViewRecord
{
    protected static string $resource = FormFieldResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
