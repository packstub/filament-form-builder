<?php

namespace Packstub\FormBuilder\Filament\Resources\FormResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Packstub\FormBuilder\FormBuilderPlugin;

class ListForms extends ListRecords
{
    public static function getResource(): string
    {
        return FormBuilderPlugin::get()->getResource();
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
