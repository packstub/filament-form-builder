<?php

namespace Packstub\FormBuilder\Notifications;

use Packstub\FormBuilder\Models\FormSubmission;

/**
 * The tags a subject or a message body may carry: {field_key} or
 * {{ field_key }} for any submitted value, plus {form_name},
 * {submission_number}, {submission_id}, {date} and {domain}.
 */
final class MergeTags
{
    /**
     * @return array<string, string>
     */
    public static function for(FormSubmission $submission): array
    {
        $form = $submission->form;
        $tags = [
            'form_name' => $form->name,
            'form_slug' => $form->slug,
            'submission_number' => (string) ($submission->number ?? $submission->getKey() ?? ''),
            'submission_id' => (string) ($submission->getKey() ?? ''),
            'date' => ($submission->created_at ?? now())->format((string) config('packstub-form-builder.formats.datetime', 'Y-m-d H:i')),
            'domain' => (string) parse_url((string) config('app.url'), PHP_URL_HOST),
        ];

        foreach ($submission->formatted() as $key => $row) {
            $tags[$key] = $row['value'];
        }

        return $tags;
    }

    /**
     * The text with its tags filled in. With $markdown (a Markdown mail
     * body), every value is escaped first, so what a visitor typed shows
     * as typed and never becomes HTML, a link or formatting.
     */
    public static function render(?string $text, FormSubmission $submission, bool $markdown = false): string
    {
        if ($text === null || $text === '') {
            return '';
        }

        $tags = self::for($submission);

        if ($markdown) {
            $tags = array_map(self::markdown(...), $tags);
        }

        return (string) preg_replace_callback('/\{\{\s*([a-z0-9_]+)\s*\}\}|\{([a-z0-9_]+)\}/i', function (array $match) use ($tags): string {
            $key = $match[1] !== '' ? $match[1] : $match[2];

            return $tags[$key] ?? $match[0];
        }, $text);
    }

    /**
     * A value as literal Markdown text: HTML escaped (no tags, no
     * autolinks), the characters of emphasis, code, links and tables
     * backslash-escaped, and line breaks turned into spaces so a value
     * can't start a block of its own.
     */
    public static function markdown(string $value): string
    {
        $value = preg_replace('/\s*\R\s*/u', ' ', $value) ?? $value;
        $value = (string) preg_replace('/([\\\\`*_\[\]|~])/', '\\\\$1', $value);

        return e($value);
    }
}
