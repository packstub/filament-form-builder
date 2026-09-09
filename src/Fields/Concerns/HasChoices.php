<?php

namespace Packstub\FormBuilder\Fields\Concerns;

use Filament\Forms\Components\KeyValue;
use Filament\Schemas\Components\Component;
use Illuminate\Validation\Rule;
use Packstub\FormBuilder\Fields\Field;

trait HasChoices
{
    public function hasChoices(): bool
    {
        return true;
    }

    /**
     * @return array<int, Component>
     */
    public function editorSchema(): array
    {
        return [
            KeyValue::make('choices')
                ->label(__('packstub-form-builder::form-builder.editor.choices'))
                ->keyLabel(__('packstub-form-builder::form-builder.editor.choice_value'))
                ->valueLabel(__('packstub-form-builder::form-builder.editor.choice_label'))
                ->addActionLabel(__('packstub-form-builder::form-builder.editor.add_choice'))
                ->reorderable()
                ->required()
                ->columnSpanFull(),
        ];
    }

    /**
     * @return array<int, mixed>
     */
    protected function choiceRules(Field $field): array
    {
        $values = array_keys($field->choices());

        return $values === [] ? [] : [Rule::in($values)];
    }
}
