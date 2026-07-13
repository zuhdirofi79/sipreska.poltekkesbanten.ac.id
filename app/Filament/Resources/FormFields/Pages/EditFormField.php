<?php

namespace App\Filament\Resources\FormFields\Pages;

use App\Filament\Resources\FormFields\FormFieldResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditFormField extends EditRecord
{
    protected static string $resource = FormFieldResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
