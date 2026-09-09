<?php

use Packstub\FormBuilder\Facades\FormBuilder;
use Packstub\FormBuilder\Fields\Field;
use Packstub\FormBuilder\Fields\FieldTypeRegistry;
use Packstub\FormBuilder\Fields\Types\TextField;
use Packstub\FormBuilder\Tests\Fixtures\RatingField;

it('registers the built-in field types from the config', function (): void {
    $registry = app(FieldTypeRegistry::class);

    expect($registry->all()->keys()->all())->toContain('text', 'email', 'select', 'checkboxes', 'heading')
        ->and($registry->get('email')->label())->toBe('Email')
        ->and($registry->get('heading')->isInput())->toBeFalse()
        ->and($registry->get('checkboxes')->acceptsMultiple())->toBeTrue()
        ->and($registry->find('nope'))->toBeNull();
});

it('registers custom field types and forgets built-in ones', function (): void {
    FormBuilder::registerFieldTypes([RatingField::class]);
    FormBuilder::fieldTypes()->forget([TextField::class, 'url']);

    $registry = app(FieldTypeRegistry::class);

    expect($registry->has('rating'))->toBeTrue()
        ->and($registry->has('text'))->toBeFalse()
        ->and($registry->has('url'))->toBeFalse()
        ->and($registry->get('rating')->label())->toBe('Rating');
});

it('rejects classes that are not field types', function (): void {
    app(FieldTypeRegistry::class)->register([stdClass::class]);
})->throws(InvalidArgumentException::class);

it('builds a field from a builder item with a key derived from the label', function (): void {
    $field = Field::fromArray(field('email', 'Work email', ['required' => true, 'rules' => 'max:100|ends_with:.com']), app(FieldTypeRegistry::class));

    expect($field)->not->toBeNull()
        ->and($field->key)->toBe('work_email')
        ->and($field->required)->toBeTrue()
        ->and($field->rules())->toBe(['required', 'string', 'max:255', 'email', 'max:100', 'ends_with:.com'])
        ->and($field->toArray()['type'])->toBe('email');
});

it('normalises choices from a map or a list of rows', function (): void {
    $registry = app(FieldTypeRegistry::class);

    $map = Field::fromArray(field('select', 'Topic', ['choices' => ['a' => 'A', 'b' => 'B']]), $registry);
    $rows = Field::fromArray(field('select', 'Topic', ['choices' => [['value' => 'a', 'label' => 'A'], ['label' => 'Only label']]]), $registry);

    expect($map->choices())->toBe(['a' => 'A', 'b' => 'B'])
        ->and($rows->choices())->toBe(['a' => 'A', 'Only label' => 'Only label']);
});

it('skips items of unknown types', function (): void {
    expect(Field::fromArray(['type' => 'missing', 'data' => ['label' => 'x']], app(FieldTypeRegistry::class)))->toBeNull();
});

it('formats values through the field type', function (): void {
    $registry = app(FieldTypeRegistry::class);
    $checkboxes = Field::fromArray(field('checkboxes', 'Interests', ['choices' => ['php' => 'PHP', 'js' => 'JS']]), $registry);
    $checkbox = Field::fromArray(field('checkbox', 'Newsletter'), $registry);

    expect($checkboxes->type->format(['php', 'js'], $checkboxes))->toBe('PHP, JS')
        ->and($checkbox->type->format(true, $checkbox))->toBe('Yes')
        ->and($checkbox->type->format(false, $checkbox))->toBe('No');
});
