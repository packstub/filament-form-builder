<?php

namespace Packstub\FormBuilder\Fields\Concerns;

use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Validation\Rule;
use Packstub\FormBuilder\Fields\ChoiceSources;
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
        $sources = app(ChoiceSources::class)->options();
        $manual = fn (Get $get): bool => blank($get('choices_source'));

        return [
            Select::make('choices_source')
                ->label(__('packstub-form-builder::form-builder.editor.choices_source'))
                ->helperText(__('packstub-form-builder::form-builder.editor.choices_source_hint'))
                ->options($sources)
                ->placeholder(__('packstub-form-builder::form-builder.editor.choices_manual'))
                ->native(false)
                ->live()
                ->visible($sources !== [])
                ->columnSpanFull(),
            KeyValue::make('choices')
                ->label(__('packstub-form-builder::form-builder.editor.choices'))
                ->keyLabel(__('packstub-form-builder::form-builder.editor.choice_value'))
                ->valueLabel(__('packstub-form-builder::form-builder.editor.choice_label'))
                ->addActionLabel(__('packstub-form-builder::form-builder.editor.add_choice'))
                ->reorderable()
                ->required($manual)
                ->visible($manual)
                ->columnSpanFull(),
            ...($this->hasChoiceColumns() ? [
                Select::make('columns')
                    ->label(__('packstub-form-builder::form-builder.editor.choice_columns'))
                    ->helperText(__('packstub-form-builder::form-builder.editor.choice_columns_hint'))
                    ->options(array_combine(range(1, 4), array_map('strval', range(1, 4))))
                    ->default(1)
                    ->selectablePlaceholder(false),
            ] : []),
        ];
    }

    /**
     * Whether the choices can be laid out in several columns (radio buttons, checkbox list).
     */
    public function hasChoiceColumns(): bool
    {
        return false;
    }

    /**
     * The number of columns the choices are laid out in, from 1 to 4.
     */
    public function choiceColumns(Field $field): int
    {
        return $this->hasChoiceColumns() ? max(1, min(4, (int) ($field->option('columns') ?: 1))) : 1;
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
