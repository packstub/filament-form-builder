<?php

use Packstub\FormBuilder\Livewire\FormBuilderForm;
use Packstub\FormBuilder\Models\Form;

use function Pest\Livewire\livewire;

/**
 * The compiled stylesheet of the Livewire renderer (resources/dist/livewire.css)
 * is a cherry-pick of Filament's component files. This guards the pick: every
 * class the renderer prints that Filament's full panel theme styles must have
 * a rule in the slim build too, otherwise a field type or a Filament update
 * has started printing a component the pick does not cover.
 */
it('styles every Filament class the Livewire renderer prints', function (): void {
    $slim = file_get_contents(__DIR__.'/../../resources/dist/livewire.css');
    $panel = file_get_contents(__DIR__.'/../../vendor/filament/filament/dist/theme.css');

    Form::query()->create([
        'name' => 'Every type',
        'slug' => 'every-type',
        'fields' => [
            field('heading', 'Heading'),
            field('paragraph', 'Paragraph', ['content' => 'Some text']),
            field('text', 'Text', ['required' => true, 'width' => 'half', 'placeholder' => 'Hint', 'help' => 'Help']),
            field('email', 'Email', ['required' => true, 'width' => 'half']),
            field('phone', 'Phone'),
            field('url', 'Url'),
            field('number', 'Number', ['min' => 1, 'max' => 10]),
            field('textarea', 'Textarea', ['required' => true]),
            field('select', 'Select', ['required' => true, 'choices' => ['a' => 'A', 'b' => 'B']]),
            field('radio', 'Radio', ['required' => true, 'choices' => ['a' => 'A', 'b' => 'B']]),
            field('checkbox', 'Checkbox', ['required' => true]),
            field('checkboxes', 'Checkboxes', ['required' => true, 'choices' => ['a' => 'A', 'b' => 'B']]),
            field('date', 'Date', ['required' => true]),
            field('hidden', 'Hidden', ['default' => 'x']),
        ],
    ]);

    $component = livewire(FormBuilderForm::class, ['form' => 'every-type']);
    $html = $component->html();

    $component->call('submit')->assertHasFormErrors();
    $html .= $component->html();

    preg_match_all('/(?<![\w:-])((?:[a-z0-9]+:)*fi-[a-z0-9-]+)/', $html, $matches);
    $printed = array_unique($matches[0]);

    expect($printed)->not->toBeEmpty();

    $missing = array_values(array_filter($printed, function (string $class) use ($slim, $panel): bool {
        $selector = '.'.str_replace(':', '\\:', $class);

        return str_contains($panel, $selector) && ! str_contains($slim, $selector);
    }));

    expect($missing)->toBe([]);
});
