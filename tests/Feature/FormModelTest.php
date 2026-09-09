<?php

use Illuminate\Validation\Rules\In;
use Packstub\FormBuilder\Models\Form;

it('derives the slug from the name and unique keys from the labels', function (): void {
    $form = Form::query()->create([
        'name' => 'Job Application',
        'fields' => [
            field('text', 'Name'),
            field('text', 'Name'),
            field('text', 'Name', ['key' => 'Full Name']),
            field('heading', 'Section'),
            ['type' => 'missing', 'data' => ['label' => 'dropped']],
        ],
    ]);

    expect($form->slug)->toBe('job-application')
        ->and(collect($form->fields)->pluck('data.key')->all())->toBe(['name', 'name_2', 'full_name', 'section'])
        ->and(count($form->fields))->toBe(4)
        ->and($form->inputFields()->keys()->all())->toBe(['name', 'name_2', 'full_name']);
});

it('builds validation rules and attributes from the fields', function (): void {
    $form = contactForm();

    $rules = $form->validationRules();

    expect($rules['name'])->toBe(['required', 'string', 'max:255'])
        ->and($rules['email'])->toBe(['required', 'string', 'max:255', 'email'])
        ->and($rules['topic'][0])->toBe('nullable')
        ->and($rules['topic'][1])->toBeInstanceOf(In::class)
        ->and($rules['interests'])->toBe(['nullable', 'array'])
        ->and($rules['interests.*'][0])->toBeInstanceOf(In::class)
        ->and($rules['newsletter'])->toBe(['nullable'])
        ->and($rules)->not->toHaveKey('almost_done')
        ->and($form->validationAttributes()['message'])->toBe('Message');
});

it('knows when it is closed', function (): void {
    $form = contactForm();

    expect($form->isAccepting())->toBeTrue();

    $form->update(['is_active' => false]);
    expect($form->closedReason())->toBe('This form is closed.');

    $form->update(['is_active' => true, 'opens_at' => now()->addDay()]);
    expect($form->closedReason())->toBe('This form is not open yet.');

    $form->update(['opens_at' => now()->subDay(), 'closes_at' => now()->subHour()]);
    expect($form->closedReason())->toBe('This form is closed.');

    $form->update(['closes_at' => now()->addHour()]);
    expect($form->isAccepting())->toBeTrue();
});

it('exposes a JSON definition', function (): void {
    $definition = contactForm()->toDefinition();

    expect($definition['slug'])->toBe('contact')
        ->and($definition['submit_url'])->toBe(url('/forms/contact'))
        ->and($definition['fields'])->toHaveCount(8)
        ->and($definition['fields'][2]['choices'])->toBe(['sales' => 'Sales', 'support' => 'Support'])
        ->and($definition['fields'][7]['input'])->toBeFalse();
});
