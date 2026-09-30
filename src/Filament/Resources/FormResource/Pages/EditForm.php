<?php

namespace Packstub\FormBuilder\Filament\Resources\FormResource\Pages;

use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Packstub\FormBuilder\Filament\FormActions;
use Packstub\FormBuilder\Filament\Widgets\SubmissionsChart;
use Packstub\FormBuilder\FormBuilderPlugin;
use Packstub\FormBuilder\Models\Form;

class EditForm extends EditRecord
{
    public static function getResource(): string
    {
        return FormBuilderPlugin::get()->getResource();
    }

    protected function getHeaderWidgets(): array
    {
        $record = $this->getRecord();

        return $record instanceof Form && $record->submissions()->exists() ? [SubmissionsChart::class] : [];
    }

    protected function getHeaderActions(): array
    {
        return [
            FormActions::preview(),
            FormActions::share(),
            Action::make('open')
                ->label(__('packstub-form-builder::form-builder.actions.open_page'))
                ->icon('heroicon-o-arrow-top-right-on-square')
                ->color('gray')
                ->url(fn (): ?string => $this->getRecord() instanceof Form ? ($this->getRecord()->isPrivate() ? $this->getRecord()->shareUrl() : $this->getRecord()->pageUrl()) : null, shouldOpenInNewTab: true)
                ->visible(fn (): bool => $this->getRecord() instanceof Form && $this->getRecord()->pageUrl() !== null),
            ActionGroup::make([
                FormActions::duplicate(),
                FormActions::export(),
                DeleteAction::make(),
            ]),
        ];
    }
}
