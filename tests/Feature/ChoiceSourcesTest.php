<?php

use Illuminate\Contracts\Queue\Job;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Support\Facades\Blade;
use Illuminate\Validation\ValidationException;
use Packstub\FormBuilder\Contracts\ChoiceSource;
use Packstub\FormBuilder\Facades\FormBuilder;
use Packstub\FormBuilder\Fields\ChoiceSources;
use Packstub\FormBuilder\Fields\Field;
use Packstub\FormBuilder\Fields\FieldTypeRegistry;
use Packstub\FormBuilder\Models\Form;
use Packstub\FormBuilder\Submissions\SubmissionContext;
use Packstub\FormBuilder\Submissions\Submitter;

class CourseSource implements ChoiceSource
{
    public static array $courses = ['php' => 'PHP basics', 'go' => 'Go in a week'];

    public function label(): string
    {
        return 'Courses';
    }

    public function choices(Field $field): iterable
    {
        return self::$courses;
    }
}

function sourcedForm(string $type = 'select', string $source = 'courses'): Form
{
    return Form::query()->create([
        'name' => 'Enrol',
        'slug' => 'enrol',
        'fields' => [
            field($type, 'Course', ['key' => 'course', 'choices_source' => $source, 'choices' => ['typed' => 'Typed'], 'required' => true]),
        ],
    ]);
}

beforeEach(function (): void {
    CourseSource::$courses = ['php' => 'PHP basics', 'go' => 'Go in a week'];
    FormBuilder::choices('courses', CourseSource::class);
});

it('takes the choices from a registered source instead of the typed list', function (): void {
    $form = sourcedForm();

    expect($form->field('course')->choices())->toBe(['php' => 'PHP basics', 'go' => 'Go in a week'])
        ->and($form->toDefinition()['fields'][0]['choices'])->toBe(['php' => 'PHP basics', 'go' => 'Go in a week']);

    $html = Blade::render('<x-form-builder::form form="enrol" />');

    expect($html)->toContain('<option value="go"')->toContain('Go in a week')->not->toContain('Typed');
});

it('validates against the live list and stores the key', function (): void {
    $form = sourcedForm();
    $submitter = app(Submitter::class);

    expect(fn () => $submitter->submit($form, ['course' => 'typed'], (new SubmissionContext)->trusted()))
        ->toThrow(ValidationException::class);

    $submission = $submitter->submit($form, ['course' => 'go'], (new SubmissionContext)->trusted())->submission;

    expect($submission->data['course'])->toBe('go')
        ->and($submission->formatted()['course']['value'])->toBe('Go in a week');

    // The record is gone: the stored key shows instead of the label.
    CourseSource::$courses = ['php' => 'PHP basics'];
    app(ChoiceSources::class)->flush();

    expect($submission->fresh()->formatted()['course']['value'])->toBe('go');
});

it('accepts closures and arrays, and lists the sources for the builder', function (): void {
    FormBuilder::choices('rooms', fn (Field $field): array => ['a' => 'Room A for '.$field->key], 'Meeting rooms');
    FormBuilder::choices('sizes', ['s' => 'Small', 'm' => 'Medium']);

    expect(app(ChoiceSources::class)->options())->toBe(['courses' => 'Courses', 'rooms' => 'Meeting rooms', 'sizes' => 'Sizes']);

    $form = Form::fromArray(['name' => 'Book', 'fields' => [
        field('radio', 'Room', ['key' => 'room', 'choices_source' => 'rooms']),
        field('checkboxes', 'Sizes', ['key' => 'sizes', 'choices_source' => 'sizes']),
    ]]);

    expect($form->field('room')->choices())->toBe(['a' => 'Room A for room'])
        ->and($form->field('sizes')->choices())->toBe(['s' => 'Small', 'm' => 'Medium']);
});

it('registers sources from the config', function (): void {
    $this->rebootWith(['packstub-form-builder.choice_sources' => ['courses' => CourseSource::class, 'plans' => ['free' => 'Free']]]);

    expect(app(ChoiceSources::class)->options())->toBe(['courses' => 'Courses', 'plans' => 'Plans']);
});

it('keeps an unknown source empty rather than falling back to the typed list', function (): void {
    $form = sourcedForm(source: 'missing');

    expect($form->field('course')->choices())->toBe([]);
});

it('offers the source select before the typed list in the builder', function (): void {
    $names = fn (): array => collect(app(FieldTypeRegistry::class)->get('select')->editorSchema())->map->getName()->all();

    expect($names())->toBe(['choices_source', 'choices']);
});

it('resolves a source per field definition and forgets the choices after each request and job', function (): void {
    $calls = 0;
    FormBuilder::choices('levels', function (Field $field) use (&$calls): array {
        $calls++;

        return $field->option('advanced') ? ['pro' => 'Pro'] : ['basic' => 'Basic'];
    });

    $sources = app(ChoiceSources::class);
    $basic = Form::fromArray(['name' => 'Basic', 'fields' => [field('select', 'Level', ['key' => 'level'])]])->field('level');
    $advanced = Form::fromArray(['name' => 'Advanced', 'fields' => [field('select', 'Level', ['key' => 'level', 'advanced' => true])]])->field('level');

    expect($sources->resolve('levels', $basic))->toBe(['basic' => 'Basic'])
        ->and($sources->resolve('levels', $advanced))->toBe(['pro' => 'Pro'])
        ->and($sources->resolve('levels', $basic))->toBe(['basic' => 'Basic'])
        ->and($calls)->toBe(2);

    event(new JobProcessed('sync', Mockery::mock(Job::class)));
    $sources->resolve('levels', $basic);

    expect($calls)->toBe(3);
});
