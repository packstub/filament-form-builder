<?php

namespace Packstub\FormBuilder\Filament;

use Filament\Forms\Components\Builder;
use Filament\Forms\Components\Builder\Block;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Support\Str;
use Packstub\FormBuilder\Fields\FieldType;
use Packstub\FormBuilder\Fields\FieldTypeRegistry;

/**
 * The Builder field that edits a form's fields: one block per field type,
 * each with the common settings followed by the type's own.
 */
class FieldBlocks
{
    public static function make(string $name = 'fields'): Builder
    {
        $registry = app(FieldTypeRegistry::class);

        return Builder::make($name)
            ->label(__('packstub-form-builder::form-builder.fields.fields'))
            ->hiddenLabel()
            ->blocks($registry->all()->map(fn (FieldType $type): Block => static::block($type))->values()->all())
            ->addActionLabel(__('packstub-form-builder::form-builder.editor.add_field'))
            ->blockNumbers(false)
            ->blockIcons()
            ->blockPickerColumns(2)
            ->collapsible()
            ->cloneable()
            ->reorderableWithButtons()
            ->rule(static::uniqueKeysRule())
            ->columnSpanFull();
    }

    public static function block(FieldType $type): Block
    {
        return Block::make($type::id())
            ->label(fn (?array $state): string => filled($state['label'] ?? null)
                ? $state['label'].' · '.$type->label()
                : $type->label())
            ->icon($type->icon())
            ->schema([
                Grid::make(2)->schema([
                    ...static::commonSchema($type),
                    ...$type->editorSchema(),
                    ...static::rulesSchema($type),
                ]),
            ]);
    }

    /**
     * @return array<int, Component>
     */
    protected static function commonSchema(FieldType $type): array
    {
        $schema = [
            TextInput::make('label')
                ->label(__('packstub-form-builder::form-builder.editor.label'))
                ->required()
                ->maxLength(255)
                ->live(onBlur: true)
                ->afterStateUpdated(function (?string $state, Get $get, Set $set): void {
                    if (blank($get('key'))) {
                        $set('key', Str::slug((string) $state, '_'));
                    }
                }),
        ];

        if ($type->isInput()) {
            $schema[] = TextInput::make('key')
                ->label(__('packstub-form-builder::form-builder.editor.key'))
                ->helperText(__('packstub-form-builder::form-builder.editor.key_hint'))
                ->maxLength(64)
                ->regex('/^[a-z0-9_]*$/')
                ->dehydrateStateUsing(fn (?string $state): string => Str::slug((string) $state, '_'));
        }

        if ($type->hasCommonSettings()) {
            $schema[] = TextInput::make('placeholder')
                ->label(__('packstub-form-builder::form-builder.editor.placeholder'))
                ->maxLength(255)
                ->hidden(fn (): bool => in_array($type::id(), ['checkbox', 'checkboxes', 'radio', 'date'], true));

            $schema[] = TextInput::make('hint')
                ->label(__('packstub-form-builder::form-builder.editor.hint'))
                ->maxLength(255);

            if (! in_array($type::id(), ['checkbox', 'checkboxes'], true)) {
                $schema[] = TextInput::make('default')
                    ->label(__('packstub-form-builder::form-builder.editor.default'))
                    ->maxLength(255);
            }

            $schema[] = Toggle::make('required')
                ->label(__('packstub-form-builder::form-builder.editor.required'))
                ->inline(false);

            $schema[] = Select::make('width')
                ->label(__('packstub-form-builder::form-builder.editor.width'))
                ->options([
                    'full' => __('packstub-form-builder::form-builder.editor.width_full'),
                    'half' => __('packstub-form-builder::form-builder.editor.width_half'),
                ])
                ->default('full')
                ->native(false);
        }

        return $schema;
    }

    /**
     * @return array<int, Component>
     */
    protected static function rulesSchema(FieldType $type): array
    {
        if (! $type->isInput() || $type::id() === 'hidden') {
            return [];
        }

        return [
            TagsInput::make('rules')
                ->label(__('packstub-form-builder::form-builder.editor.rules'))
                ->helperText(__('packstub-form-builder::form-builder.editor.rules_hint'))
                ->placeholder('max:100')
                ->default([])
                ->columnSpanFull(),
        ];
    }

    protected static function uniqueKeysRule(): \Closure
    {
        return fn (): \Closure => function (string $attribute, mixed $value, \Closure $fail): void {
            $registry = app(FieldTypeRegistry::class);
            $seen = [];

            foreach ((array) $value as $item) {
                $type = $registry->find((string) ($item['type'] ?? ''));

                if ($type === null || ! $type->isInput()) {
                    continue;
                }

                $key = Str::slug((string) ($item['data']['key'] ?? ''), '_') ?: Str::slug((string) ($item['data']['label'] ?? ''), '_');

                if ($key !== '' && isset($seen[$key])) {
                    $fail(__('packstub-form-builder::form-builder.editor.duplicate_key', ['key' => $key]));

                    return;
                }

                $seen[$key] = true;
            }
        };
    }
}
