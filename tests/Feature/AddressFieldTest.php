<?php

use Illuminate\Support\Facades\Blade;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Packstub\FormBuilder\Filament\SubmissionsExport;
use Packstub\FormBuilder\Livewire\FormBuilderForm;
use Packstub\FormBuilder\Models\Form;
use Packstub\FormBuilder\Submissions\SubmissionContext;
use Packstub\FormBuilder\Submissions\Submitter;

function addressForm(array $data = []): Form
{
    return Form::query()->create([
        'name' => 'Order',
        'slug' => 'order',
        'fields' => [
            field('text', 'Name', ['key' => 'name']),
            field('address', 'Shipping address', ['key' => 'shipping', 'required' => true, 'countries' => 'de, fr', ...$data]),
        ],
    ]);
}

it('validates the required parts and stores the address as an object', function (): void {
    $form = addressForm();
    $submitter = app(Submitter::class);

    try {
        $submitter->submit($form, ['shipping' => ['line1' => 'Main St 1', 'country' => 'us']], (new SubmissionContext)->trusted());
        $this->fail('Expected a validation error.');
    } catch (ValidationException $e) {
        expect(array_keys($e->errors()))->toEqualCanonicalizing(['shipping.city', 'shipping.postal_code', 'shipping.country'])
            ->and($e->errors()['shipping.city'][0])->toContain('Shipping address (city)');
    }

    $submission = $submitter->submit($form, ['shipping' => [
        'line1' => ' Main St 1 ', 'line2' => '', 'city' => 'Berlin', 'region' => null, 'postal_code' => '10115', 'country' => 'de',
    ]], (new SubmissionContext)->trusted())->submission;

    expect($submission->data['shipping'])->toBe([
        'line1' => 'Main St 1', 'line2' => null, 'city' => 'Berlin', 'region' => null, 'postal_code' => '10115', 'country' => 'DE',
    ])->and($submission->formatted()['shipping']['value'])->toBe('Main St 1, 10115 Berlin, Germany');
});

it('keeps an optional empty address null and only asks for the chosen parts', function (): void {
    $form = addressForm(['required' => false, 'parts' => ['line1', 'city']]);

    $data = app(Submitter::class)->submit($form, ['shipping' => ['line1' => '', 'city' => '']], (new SubmissionContext)->trusted())->submission->data;

    expect($data['shipping'])->toBeNull()
        ->and($form->validationRules())->toHaveKeys(['shipping', 'shipping.line1', 'shipping.city'])
        ->and($form->validationRules())->not->toHaveKey('shipping.country');
});

it('renders the parts in the Blade renderer and shows a part error on the field', function (): void {
    addressForm();

    $html = Blade::render('<x-form-builder::form form="order" />');

    expect($html)->toContain('name="shipping[line1]"')
        ->toContain('autocomplete="postal-code"')
        ->toContain('<option value="FR"')
        ->not->toContain('<option value="US"');

    $this->from('/forms/order')->post('/forms/order', ['shipping' => ['line1' => 'Main St 1']])
        ->assertRedirect('/forms/order#form-order');

    $html = $this->get('/forms/order')->getContent();

    expect($html)->toContain('fb-field--error')
        ->toContain('The Shipping address (city) field is required.')
        ->toContain('value="Main St 1"');
});

it('exposes the parts in the JSON definition and splits the export columns', function (): void {
    $form = addressForm();
    $definition = collect($form->toDefinition()['fields'])->firstWhere('key', 'shipping');

    expect($definition['parts'])->toHaveKeys(['line1', 'city', 'country'])
        ->and($definition['required_parts'])->toBe(['line1', 'city', 'postal_code', 'country'])
        ->and($definition['countries'])->toBe(['DE' => 'Germany', 'FR' => 'France']);

    app(Submitter::class)->submit($form, ['name' => 'Ada', 'shipping' => [
        'line1' => 'Main St 1', 'city' => 'Paris', 'postal_code' => '75001', 'country' => 'FR',
    ]], (new SubmissionContext)->trusted());

    [$header, $rows] = SubmissionsExport::rows($form, $form->submissions()->getQuery());
    $row = array_combine($header, iterator_to_array($rows)[0]);

    expect($header)->toContain('Shipping address (street address)', 'Shipping address (country)')
        ->and($row['Shipping address (city)'])->toBe('Paris')
        ->and($row['Shipping address (country)'])->toBe('France')
        ->and($row['Name'])->toBe('Ada');
});

it('renders a fieldset of inputs in the Livewire renderer and submits it', function (): void {
    $form = addressForm();

    Livewire::test(FormBuilderForm::class, ['form' => 'order'])
        ->set('data.shipping.line1', 'Main St 1')
        ->set('data.shipping.city', 'Berlin')
        ->set('data.shipping.postal_code', '10115')
        ->set('data.shipping.country', 'DE')
        ->call('submit')
        ->assertHasNoErrors()
        ->assertSet('submitted', true);

    expect($form->submissions()->first()->data['shipping']['city'])->toBe('Berlin');
});
