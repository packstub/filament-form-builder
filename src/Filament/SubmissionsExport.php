<?php

namespace Packstub\FormBuilder\Filament;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Packstub\FormBuilder\Models\Form;
use Packstub\FormBuilder\Models\FormSubmission;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Submissions as CSV or, when OpenSpout is installed (Filament's export
 * action brings it), as an Excel workbook: the form's fields first (hidden
 * ones included), then any key an older submission still carries, then the
 * metadata columns.
 */
class SubmissionsExport
{
    public static function hasXlsx(): bool
    {
        return class_exists(Writer::class);
    }

    /**
     * @param  Builder<FormSubmission>|Collection<int, FormSubmission>  $submissions
     */
    public static function download(Form $form, Builder|Collection $submissions, string $format = 'csv'): StreamedResponse
    {
        $format = $format === 'xlsx' && static::hasXlsx() ? 'xlsx' : 'csv';
        $filename = Str::slug($form->slug.'-submissions-'.now()->format('Y-m-d')).'.'.$format;

        return response()->streamDownload(
            fn () => $format === 'xlsx' ? static::writeXlsx($form, $submissions) : static::write($form, $submissions),
            $filename,
            ['Content-Type' => $format === 'xlsx' ? 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' : 'text/csv; charset=UTF-8'],
        );
    }

    /**
     * The header row and the data rows.
     *
     * @param  Builder<FormSubmission>|Collection<int, FormSubmission>  $submissions
     * @return array{0: array<int, string>, 1: iterable<int, array<int, mixed>>}
     */
    public static function rows(Form $form, Builder|Collection $submissions): array
    {
        $records = $submissions instanceof Builder ? $submissions->orderBy('created_at')->get() : $submissions;
        $keys = $form->allInputFields()->keys()->all();

        foreach ($records as $record) {
            foreach (array_keys($record->data ?? []) as $key) {
                if (! in_array($key, $keys, true)) {
                    $keys[] = $key;
                }
            }
        }

        // A field whose type splits its value (an address) gets one column per part.
        $fields = $form->allInputFields();
        $columns = [];

        foreach ($keys as $key) {
            $field = $fields->get($key);
            $parts = $field === null ? [] : $field->type->exportColumns($field);

            if ($parts === []) {
                $columns[] = ['key' => $key, 'part' => null, 'label' => $field?->label ?? $key];

                continue;
            }

            foreach ($parts as $part => $label) {
                $columns[] = ['key' => $key, 'part' => $part, 'label' => $label];
            }
        }

        $header = ['number', 'id', 'submitted_at', ...array_column($columns, 'label'), 'source_url', 'ip', 'user_id'];

        $rows = (function () use ($records, $columns, $fields): \Generator {
            foreach ($records as $record) {
                $formatted = $record->formatted();

                yield [
                    $record->number,
                    $record->getKey(),
                    $record->created_at?->toDateTimeString(),
                    ...array_map(fn (array $column): string => $column['part'] === null
                        ? ($formatted[$column['key']]['value'] ?? '')
                        : $fields->get($column['key'])->type->exportValue($record->value($column['key']), $fields->get($column['key']), $column['part']), $columns),
                    $record->source_url,
                    $record->ip,
                    $record->user_id,
                ];
            }
        })();

        return [$header, $rows];
    }

    /**
     * @param  Builder<FormSubmission>|Collection<int, FormSubmission>  $submissions
     */
    public static function write(Form $form, Builder|Collection $submissions, mixed $handle = null): void
    {
        $handle ??= fopen('php://output', 'w');
        [$header, $rows] = static::rows($form, $submissions);

        fputcsv($handle, $header);

        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }

        if ($handle !== null && is_resource($handle) && stream_get_meta_data($handle)['uri'] === 'php://output') {
            fclose($handle);
        }
    }

    /**
     * @param  Builder<FormSubmission>|Collection<int, FormSubmission>  $submissions
     */
    public static function writeXlsx(Form $form, Builder|Collection $submissions, ?string $path = null): void
    {
        [$header, $rows] = static::rows($form, $submissions);

        $writer = new Writer;
        $path === null ? $writer->openToFile('php://output') : $writer->openToFile($path);
        $writer->addRow(Row::fromValues($header));

        foreach ($rows as $row) {
            $writer->addRow(Row::fromValues(array_map(fn ($value) => $value ?? '', $row)));
        }

        $writer->close();
    }
}
