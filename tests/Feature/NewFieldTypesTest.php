<?php

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Packstub\FormBuilder\Fields\Countries;
use Packstub\FormBuilder\Fields\Field;
use Packstub\FormBuilder\Fields\FieldTypeRegistry;
use Packstub\FormBuilder\Models\Form;
use Packstub\FormBuilder\Models\FormSubmission;
use Packstub\FormBuilder\Submissions\SubmissionContext;
use Packstub\FormBuilder\Submissions\Submitter;
use Packstub\FormBuilder\Uploads\Uploads;

function everyTypeForm(): Form
{
    return Form::query()->create([
        'name' => 'Every type',
        'slug' => 'every-type',
        'fields' => [
            field('datetime', 'When', ['key' => 'when']),
            field('time', 'At', ['key' => 'at']),
            field('multiselect', 'Tools', ['key' => 'tools', 'choices' => ['php' => 'PHP', 'js' => 'JS', 'go' => 'Go']]),
            field('toggle_buttons', 'Plan', ['key' => 'plan', 'choices' => ['free' => 'Free', 'pro' => 'Pro']]),
            field('toggle', 'Notify', ['key' => 'notify']),
            field('tags', 'Keywords', ['key' => 'keywords', 'max_items' => 3]),
            field('rating', 'Score', ['key' => 'score', 'max' => 5]),
            field('color', 'Colour', ['key' => 'colour']),
            field('currency', 'Budget', ['key' => 'budget', 'prefix' => '€', 'decimals' => 2]),
            field('richtext', 'Story', ['key' => 'story']),
            field('country', 'Country', ['key' => 'country', 'countries' => 'de, fr, xx']),
            field('consent', 'I accept the', ['key' => 'terms', 'link_text' => 'terms', 'link_url' => 'https://example.com/terms', 'required' => true]),
            field('divider', 'Or'),
            field('file', 'Attachment', ['key' => 'attachment', 'accept' => 'pdf, image/png', 'max_kb' => 64]),
        ],
    ]);
}

it('normalises and formats the values of the new types', function (): void {
    Storage::fake('local');
    $form = everyTypeForm();

    $result = app(Submitter::class)->submit($form, [
        'when' => '2026-03-04T15:30',
        'at' => '09:05:00',
        'tools' => ['php', 'go'],
        'plan' => 'pro',
        'notify' => 'on',
        'keywords' => 'laravel, filament , laravel',
        'score' => '4',
        'colour' => '#FF0000',
        'budget' => '1234.567',
        'story' => '<p>Hello <b>you</b><script>alert(1)</script><a href="javascript:x" onclick="y()">link</a></p>',
        'country' => 'de',
        'terms' => '1',
        'attachment' => [UploadedFile::fake()->create('Brief Q1.pdf', 20, 'application/pdf')],
    ], (new SubmissionContext)->trusted());

    $data = $result->submission->data;

    expect($data['when'])->toBe('2026-03-04 15:30:00')
        ->and($data['at'])->toBe('09:05')
        ->and($data['tools'])->toBe(['php', 'go'])
        ->and($data['plan'])->toBe('pro')
        ->and($data['notify'])->toBeTrue()
        ->and($data['keywords'])->toBe(['laravel', 'filament'])
        ->and($data['score'])->toBe(4)
        ->and($data['colour'])->toBe('#ff0000')
        ->and($data['budget'])->toBe(1234.57)
        ->and($data['story'])->toBe('<p>Hello <b>you</b>alert(1)<a href="#">link</a></p>')
        ->and($data['country'])->toBe('DE')
        ->and($data['terms'])->toBeTrue()
        ->and($data['attachment'])->toHaveCount(1)
        ->and($data['attachment'][0])->toStartWith('form-builder/'.$form->id.'/')
        ->and($data['attachment'][0])->toEndWith('__brief-q1.pdf');

    Storage::disk('local')->assertExists($data['attachment'][0]);

    $formatted = $result->submission->formatted();

    expect($formatted['when']['value'])->toBe('2026-03-04 15:30')
        ->and($formatted['tools']['value'])->toBe('PHP, Go')
        ->and($formatted['plan']['value'])->toBe('Pro')
        ->and($formatted['notify']['value'])->toBe('Yes')
        ->and($formatted['keywords']['value'])->toBe('laravel, filament')
        ->and($formatted['score']['value'])->toBe('4 / 5')
        ->and($formatted['budget']['value'])->toBe('€1,234.57')
        ->and($formatted['story']['value'])->toBe('Hello youalert(1)link')
        ->and($formatted['country']['value'])->toBe('Germany')
        ->and($formatted['attachment']['value'])->toBe('brief-q1.pdf')
        ->and(Uploads::originalName($data['attachment'][0]))->toBe('brief-q1.pdf');

    // Deleting the submission deletes its files.
    $result->submission->delete();
    Storage::disk('local')->assertMissing($data['attachment'][0]);
});

it('validates the new types', function (): void {
    Storage::fake('local');
    $form = everyTypeForm();

    try {
        app(Submitter::class)->validate($form, [
            'at' => '25:99',
            'tools' => ['php', 'ruby'],
            'plan' => 'enterprise',
            'keywords' => 'a, b, c, d',
            'score' => '9',
            'colour' => 'red',
            'country' => 'XX',
            'terms' => '0',
            'attachment' => [UploadedFile::fake()->create('big.pdf', 200, 'application/pdf'), UploadedFile::fake()->create('x.exe', 1, 'application/octet-stream')],
        ]);
        $this->fail('Expected a validation exception.');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKeys(['at', 'tools.1', 'plan', 'keywords', 'score', 'colour', 'country', 'terms', 'attachment', 'attachment.0', 'attachment.1']);
    }
});

it('accepts base64 uploads from the JSON API', function (): void {
    Storage::fake('local');
    everyTypeForm();

    $response = $this->postJson('/forms/every-type', [
        'terms' => true,
        'attachment' => [['name' => 'note.pdf', 'data' => 'data:application/pdf;base64,'.base64_encode('%PDF-1.4 hello')]],
    ])->assertOk();

    $submission = FormSubmission::query()->findOrFail($response->json('id'));

    expect($submission->data['attachment'][0])->toEndWith('__note.pdf');
    Storage::disk('local')->assertExists($submission->data['attachment'][0]);

    // Downloads go through a signed route.
    $url = Uploads::url($submission, 'attachment');
    $this->get($url)->assertOk()->assertDownload('note.pdf');
    $this->get(preg_replace('/signature=\w+/', 'signature=bad', $url))->assertForbidden();

    // ...and expires.
    expect($url)->toContain('expires=');
    $this->travel(1441)->minutes();
    $this->get($url)->assertForbidden();
});

it('renders every new type in the plain renderer', function (): void {
    everyTypeForm();

    $html = $this->get('/forms/every-type')->assertOk()->getContent();

    expect($html)
        ->toContain('type="datetime-local"', 'type="time"', 'name="tools[]"', 'multiple', 'class="fb-toggle-buttons"', 'role="switch"', 'fb-toggle__track')
        ->toContain('name="keywords"', 'class="fb-rating"', 'value="5"', 'type="color"', 'fb-affix__prefix', 'step="0.01"', 'inputmode="decimal"')
        ->toContain('<textarea', 'name="story"', '<option value="DE"', 'Germany', '<option value="FR"')
        ->toContain('href="https://example.com/terms"', '>terms</a>', 'class="fb-divider"', 'fb-divider__text', 'type="file"', 'accept=".pdf,image/png"', 'enctype="multipart/form-data"', 'Up to 64 KB');

    // The three tools, the placeholder and the two countries.
    expect(substr_count($html, '<option value="'))->toBe(6);
});

it('lists the countries', function (): void {
    expect(Countries::all())->toHaveCount(249)
        ->and(Countries::name('gb'))->toBe('United Kingdom')
        ->and(Countries::name('zz'))->toBeNull();

    $field = Field::fromArray(field('country', 'Country'), app(FieldTypeRegistry::class));

    expect($field->choices())->toHaveCount(249);
});

it('builds a field from a portable array and back', function (): void {
    $form = Form::fromArray(['name' => 'Ad hoc', 'fields' => [field('text', 'Name', ['required' => true]), field('rating', 'Score')], 'settings' => ['layout' => 'horizontal']]);

    expect($form->exists)->toBeFalse()
        ->and($form->slug)->toBe('ad-hoc')
        ->and($form->inputFields()->keys()->all())->toBe(['name', 'score'])
        ->and($form->layout())->toBe('horizontal')
        ->and($form->toPortable()['fields'][0]['data']['key'])->toBe('name');

    $html = Blade::render('<x-form-builder::form :form="$form" :styles="false" :enhance="false" />', ['form' => $form]);

    expect($html)->toContain('id="form-ad-hoc"', 'fb-form--horizontal', 'name="score"');
});
