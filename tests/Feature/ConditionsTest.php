<?php

use Illuminate\Validation\ValidationException;
use Packstub\FormBuilder\Fields\Conditions;
use Packstub\FormBuilder\Models\Form;
use Packstub\FormBuilder\Submissions\Submitter;

it('compares values with every operator', function (): void {
    expect(Conditions::compare('equals', 'Yes', 'yes'))->toBeTrue()
        ->and(Conditions::compare('equals', '10', '10.0'))->toBeTrue()
        ->and(Conditions::compare('equals', true, 'true'))->toBeTrue()
        ->and(Conditions::compare('equals', ['a'], 'a'))->toBeTrue()
        ->and(Conditions::compare('not_equals', 'a', 'b'))->toBeTrue()
        ->and(Conditions::compare('contains', 'Hello World', 'world'))->toBeTrue()
        ->and(Conditions::compare('contains', ['php', 'js'], 'js'))->toBeTrue()
        ->and(Conditions::compare('not_contains', ['php'], 'js'))->toBeTrue()
        ->and(Conditions::compare('greater_than', '5', '3'))->toBeTrue()
        ->and(Conditions::compare('greater_than', 'abc', '3'))->toBeFalse()
        ->and(Conditions::compare('less_than', 2, 3))->toBeTrue()
        ->and(Conditions::compare('is_empty', ''))->toBeTrue()
        ->and(Conditions::compare('is_empty', []))->toBeTrue()
        ->and(Conditions::compare('is_empty', false))->toBeTrue()
        ->and(Conditions::compare('is_not_empty', 'x'))->toBeTrue()
        ->and(Conditions::compare('nope', 'x', 'x'))->toBeFalse();
});

it('combines rules with all or any and honours the mode', function (): void {
    $data = [
        'visibility' => 'when',
        'visibility_logic' => 'all',
        'visibility_rules' => [
            ['field' => 'type', 'operator' => 'equals', 'value' => 'business'],
            ['field' => 'size', 'operator' => 'greater_than', 'value' => 10],
            ['field' => '', 'operator' => 'equals', 'value' => 'ignored'],
            ['field' => 'x', 'operator' => 'bogus', 'value' => 'ignored'],
        ],
    ];

    $all = Conditions::fromData($data, 'visibility');
    $any = Conditions::fromData([...$data, 'visibility_logic' => 'any'], 'visibility');
    $unless = Conditions::fromData([...$data, 'visibility' => 'unless'], 'visibility');

    expect($all->rules)->toHaveCount(2)
        ->and($all->fieldKeys())->toBe(['type', 'size'])
        ->and($all->passes(['type' => 'business', 'size' => 20]))->toBeTrue()
        ->and($all->passes(['type' => 'business', 'size' => 5]))->toBeFalse()
        ->and($any->passes(['type' => 'business', 'size' => 5]))->toBeTrue()
        ->and($any->passes(['type' => 'personal', 'size' => 5]))->toBeFalse()
        ->and($unless->passes(['type' => 'business', 'size' => 20]))->toBeFalse()
        ->and($unless->passes(['type' => 'personal']))->toBeTrue()
        ->and(Conditions::fromData(['visibility' => 'always'], 'visibility')->isAlways())->toBeTrue()
        ->and(Conditions::fromData(['visibility' => 'when'], 'visibility')->isAlways())->toBeTrue()
        ->and(Conditions::fromData(['visibility' => 'when'], 'visibility')->passes([]))->toBeTrue();
});

it('resolves visible fields with the cascade', function (): void {
    $form = conditionalForm();

    expect($form->visibleKeys(['attendance' => 'virtual']))->toBe(['attendance', 'company'])
        ->and($form->visibleKeys(['attendance' => 'in_person', 'tshirt' => null]))->toBe(['attendance', 'tshirt', 'company'])
        ->and($form->visibleKeys(['attendance' => 'in_person', 'tshirt' => 'm']))->toBe(['attendance', 'tshirt', 'allergies', 'company'])
        // Allergies depends on T-shirt, which is hidden for virtual attendance, even though T-shirt has a value.
        ->and($form->visibleKeys(['attendance' => 'virtual', 'tshirt' => 'm']))->toBe(['attendance', 'company'])
        ->and($form->inputFields()->keys()->all())->not->toContain('never')
        ->and($form->allInputFields()->keys()->all())->toContain('never');
});

it('validates only the visible fields and resolves conditional requirement', function (): void {
    $form = conditionalForm();
    $submitter = app(Submitter::class);

    // Virtual: T-shirt and allergies hidden (not required), company required.
    $errors = null;

    try {
        $submitter->validate($form, ['attendance' => 'virtual']);
    } catch (ValidationException $e) {
        $errors = $e->errors();
    }

    expect($errors)->toHaveKey('company')->not->toHaveKeys(['tshirt', 'allergies']);

    $data = $submitter->validate($form, ['attendance' => 'virtual', 'company' => 'Acme', 'tshirt' => 'm', 'allergies' => 'nuts']);

    expect($data)->toBe(['attendance' => 'virtual', 'tshirt' => null, 'allergies' => null, 'company' => 'Acme']);

    // In person: T-shirt required, company optional.
    $errors = null;

    try {
        $submitter->validate($form, ['attendance' => 'in_person']);
    } catch (ValidationException $e) {
        $errors = $e->errors();
    }

    expect($errors)->toHaveKey('tshirt')->not->toHaveKey('company');

    $data = $submitter->validate($form, ['attendance' => 'in_person', 'tshirt' => 'm', 'allergies' => 'none']);

    expect($data['tshirt'])->toBe('m')->and($data['allergies'])->toBe('none')->and($data['company'])->toBeNull();
});

it('exposes the conditions and the sections in the definition', function (): void {
    $form = conditionalForm();
    $definition = $form->toDefinition();
    $tshirt = collect($definition['fields'])->firstWhere('key', 'tshirt');
    $company = collect($definition['fields'])->firstWhere('key', 'company');

    expect($tshirt['visibility'])->toBe(['mode' => 'when', 'logic' => 'all', 'rules' => [['field' => 'attendance', 'operator' => 'equals', 'value' => 'in_person']]])
        ->and($tshirt['requirement'])->toBeNull()
        ->and($company['requirement']['mode'])->toBe('when')
        ->and($definition['sections'])->toHaveCount(1)
        ->and($definition['sections'][0]['fields'])->toBe(['attendance', 'tshirt', 'allergies', 'company'])
        ->and($definition['mode'])->toBe('single');
});

it('keeps a condition value inside the logic script element', function (): void {
    Form::query()->create([
        'name' => 'Logic',
        'slug' => 'logic',
        'fields' => [
            field('text', 'Name', ['key' => 'name']),
            field('text', 'Other', ['key' => 'other', 'visibility' => 'when',
                'visibility_rules' => [['field' => 'name', 'operator' => 'equals', 'value' => '</script><img src=x onerror=alert(1)>']]]),
        ],
    ]);

    $html = $this->get('/forms/logic')->assertOk()->getContent();

    expect($html)->not->toContain('<img src=x')
        ->toContain('\u003C/script\u003E\u003Cimg src=x onerror=alert(1)\u003E');
});
