<?php

namespace Packstub\FormBuilder\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Packstub\FormBuilder\FormBuilder;
use Packstub\FormBuilder\Uploads\Uploads;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Serve an uploaded file from the (private) uploads disk. The URL is signed
 * and issued by the panel only, so a file never has a guessable address.
 */
class DownloadFileController
{
    public function __invoke(Request $request, int $submission, string $field, int $index = 0): StreamedResponse
    {
        abort_unless($request->hasValidSignature(), 403);

        $record = FormBuilder::submissionModel()::query()->findOrFail($submission);
        $files = Uploads::filesOf($record, $field);
        $path = $files[$index] ?? abort(404);

        $disk = Storage::disk(Uploads::disk());

        abort_unless($disk->exists($path), 404);

        return $disk->download($path, Uploads::originalName($path));
    }
}
