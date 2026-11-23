<?php

use Illuminate\Support\Facades\Storage;
use Packstub\FormBuilder\Models\Form;
use Packstub\FormBuilder\Models\FormSubmission;
use Packstub\FormBuilder\Uploads\Uploads;

function uploadForm(string $slug = 'apply'): Form
{
    return Form::query()->create([
        'name' => ucfirst($slug),
        'slug' => $slug,
        'fields' => [field('file', 'CV', ['key' => 'cv', 'multiple' => true])],
    ]);
}

it('refuses a file path that is not one of the form\'s own uploads', function (string $path): void {
    Storage::fake('local');
    Storage::disk('local')->put('secrets/keep.txt', 'secret');
    Storage::disk('local')->put('form-builder/999/01J0000000000000000000000__other.pdf', 'other form');
    $form = uploadForm();

    $this->postJson('/forms/apply', ['cv' => [$path]])->assertUnprocessable()->assertJsonValidationErrors('cv.0');

    expect(FormSubmission::query()->count())->toBe(0);
})->with([
    'another file on the disk' => 'secrets/keep.txt',
    'another form\'s upload' => 'form-builder/999/01J0000000000000000000000__other.pdf',
    'a path climbing out' => 'form-builder/1/../../secrets/keep.txt',
    'a missing file of the form' => 'form-builder/1/01J0000000000000000000000__gone.pdf',
]);

it('never serves, attaches or deletes a stored path outside the form\'s uploads', function (): void {
    Storage::fake('local');
    Storage::disk('local')->put('secrets/keep.txt', 'secret');
    $form = uploadForm();
    $own = Uploads::directory($form).'/01J0000000000000000000000__cv.pdf';
    Storage::disk('local')->put($own, 'cv');

    // A row written before this release, or by code that skipped validation.
    $submission = FormSubmission::query()->create([
        'form_id' => $form->getKey(),
        'data' => ['cv' => ['secrets/keep.txt', $own]],
        'fields' => ['cv' => ['label' => 'CV', 'type' => 'file']],
    ]);

    expect(Uploads::filesOf($submission, 'cv'))->toBe([$own]);

    $this->get(Uploads::url($submission, 'cv', 0))->assertOk()->assertDownload('cv.pdf');
    $this->get(Uploads::url($submission, 'cv', 1))->assertNotFound();

    $submission->delete();

    expect(Storage::disk('local')->exists('secrets/keep.txt'))->toBeTrue()
        ->and(Storage::disk('local')->exists($own))->toBeFalse();
});
