<?php

namespace Packstub\FormBuilder\Filament;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Packstub\FormBuilder\Models\Form;
use Packstub\FormBuilder\Models\FormSubmission;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * A CSV of submissions: the form's current fields first, then any key an
 * older submission still carries, then the metadata columns.
 */
class SubmissionsCsv
{
    /**
     * @param  Builder<FormSubmission>|Collection<int, FormSubmission>  $submissions
     */
    public static function download(Form $form, Builder|Collection $submissions): StreamedResponse
    {
        $filename = Str::slug($form->slug.'-submissions-'.now()->format('Y-m-d')).'.csv';

        return response()->streamDownload(
            fn () => static::write($form, $submissions),
            $filename,
            ['Content-Type' => 'text/csv; charset=UTF-8'],
        );
    }

    /**
     * @param  Builder<FormSubmission>|Collection<int, FormSubmission>  $submissions
     */
    public static function write(Form $form, Builder|Collection $submissions, mixed $handle = null): void
    {
        $handle ??= fopen('php://output', 'w');
        $records = $submissions instanceof Builder ? $submissions->orderBy('created_at')->get() : $submissions;

        $keys = $form->inputFields()->keys()->all();

        foreach ($records as $record) {
            foreach (array_keys($record->data ?? []) as $key) {
                if (! in_array($key, $keys, true)) {
                    $keys[] = $key;
                }
            }
        }

        $labels = array_map(fn (string $key): string => $form->field($key)?->label ?? $key, $keys);

        fputcsv($handle, ['id', 'submitted_at', ...$labels, 'source_url', 'ip', 'user_id']);

        foreach ($records as $record) {
            $formatted = $record->formatted();

            fputcsv($handle, [
                $record->getKey(),
                $record->created_at?->toDateTimeString(),
                ...array_map(fn (string $key): string => $formatted[$key]['value'] ?? '', $keys),
                $record->source_url,
                $record->ip,
                $record->user_id,
            ]);
        }

        if ($handle !== null && is_resource($handle) && stream_get_meta_data($handle)['uri'] === 'php://output') {
            fclose($handle);
        }
    }
}
