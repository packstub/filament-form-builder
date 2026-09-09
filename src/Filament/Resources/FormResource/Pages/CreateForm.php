<?php

namespace Packstub\FormBuilder\Filament\Resources\FormResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use Packstub\FormBuilder\FormBuilderPlugin;

class CreateForm extends CreateRecord
{
    public static function getResource(): string
    {
        return FormBuilderPlugin::get()->getResource();
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('edit', ['record' => $this->getRecord()]);
    }
}
