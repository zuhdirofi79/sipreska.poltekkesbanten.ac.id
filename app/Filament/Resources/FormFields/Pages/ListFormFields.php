<?php

namespace App\Filament\Resources\FormFields\Pages;

use App\Filament\Resources\FormFields\FormFieldResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListFormFields extends ListRecords
{
    protected static string $resource = FormFieldResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
