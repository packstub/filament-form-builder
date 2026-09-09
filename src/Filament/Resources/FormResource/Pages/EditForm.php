<?php

namespace Packstub\FormBuilder\Filament\Resources\FormResource\Pages;

use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Packstub\FormBuilder\FormBuilderPlugin;
use Packstub\FormBuilder\Models\Form;

class EditForm extends EditRecord
{
    public static function getResource(): string
    {
        return FormBuilderPlugin::get()->getResource();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('open')
                ->label(__('packstub-form-builder::form-builder.actions.open_page'))
                ->icon('heroicon-o-arrow-top-right-on-square')
                ->color('gray')
                ->url(fn (): ?string => $this->getRecord()->pageUrl(), shouldOpenInNewTab: true)
                ->visible(fn (): bool => $this->getRecord() instanceof Form && $this->getRecord()->pageUrl() !== null),
            DeleteAction::make(),
        ];
    }
}
