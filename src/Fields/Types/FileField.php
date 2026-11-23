<?php

namespace Packstub\FormBuilder\Fields\Types;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Component;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Packstub\FormBuilder\Fields\Field;
use Packstub\FormBuilder\Fields\FieldType;
use Packstub\FormBuilder\Fields\ValidationRules;
use Packstub\FormBuilder\Models\Form;
use Packstub\FormBuilder\Uploads\Uploads;

/**
 * One or more files, stored on the uploads disk (config "uploads") under a
 * directory per form; the submission keeps the paths. The JSON API takes
 * base64 data URIs ({name, data}) instead of multipart parts.
 */
class FileField extends FieldType
{
    public static function id(): string
    {
        return 'file';
    }

    public function icon(): string
    {
        return 'heroicon-o-paper-clip';
    }

    public function ruleCategory(): ?string
    {
        return ValidationRules::CATEGORY_FILE;
    }

    public function hasPlaceholder(): bool
    {
        return false;
    }

    public function hasDefault(): bool
    {
        return false;
    }

    public function isComparable(): bool
    {
        return false;
    }

    public function acceptsMultiple(): bool
    {
        return true;
    }

    public function editorSchema(): array
    {
        return [
            Toggle::make('multiple')
                ->label(__('packstub-form-builder::form-builder.editor.multiple'))
                ->inline(false)
                ->live(),
            TextInput::make('max_files')
                ->label(__('packstub-form-builder::form-builder.editor.max_files'))
                ->integer()
                ->minValue(1)
                ->default(5)
                ->visible(fn ($get): bool => (bool) $get('multiple')),
            TextInput::make('accept')
                ->label(__('packstub-form-builder::form-builder.editor.accepted_types'))
                ->helperText(__('packstub-form-builder::form-builder.editor.accepted_types_hint'))
                ->placeholder('pdf, png, jpg'),
            TextInput::make('max_kb')
                ->label(__('packstub-form-builder::form-builder.editor.max_kb'))
                ->integer()
                ->minValue(1)
                ->placeholder((string) config('packstub-form-builder.uploads.max_kb', 10240)),
        ];
    }

    public function isMultiple(Field $field): bool
    {
        return (bool) $field->option('multiple', false);
    }

    public function maxKb(Field $field): int
    {
        return (int) ($field->option('max_kb') ?: config('packstub-form-builder.uploads.max_kb', 10240));
    }

    /**
     * The accepted extensions (lowercase, no dot) and MIME types.
     *
     * @return array{extensions: array<int, string>, mimes: array<int, string>}
     */
    public function accepted(Field $field): array
    {
        $extensions = [];
        $mimes = [];

        foreach (ValidationRules::list((string) $field->option('accept', '')) as $item) {
            $item = strtolower(ltrim($item, '.'));

            if ($item === '') {
                continue;
            }

            str_contains($item, '/') ? $mimes[] = $item : $extensions[] = $item;
        }

        return ['extensions' => $extensions, 'mimes' => $mimes];
    }

    /**
     * The "accept" attribute of the <input type="file">.
     */
    public function acceptAttribute(Field $field): ?string
    {
        $accepted = $this->accepted($field);
        $parts = [...array_map(fn (string $extension): string => '.'.$extension, $accepted['extensions']), ...$accepted['mimes']];

        return $parts === [] ? null : implode(',', $parts);
    }

    public function rules(Field $field): array
    {
        $rules = ['array'];

        if ($this->isMultiple($field) && filled($max = $field->option('max_files'))) {
            $rules[] = 'max:'.(int) $max;
        } elseif (! $this->isMultiple($field)) {
            $rules[] = 'max:1';
        }

        return $rules;
    }

    public function elementRules(Field $field): array
    {
        $accepted = $this->accepted($field);
        $rules = ['max:'.$this->maxKb($field)];

        if ($accepted['extensions'] !== [] || $accepted['mimes'] !== []) {
            $rules[] = function (string $attribute, mixed $value, \Closure $fail) use ($accepted): void {
                if (! ($value instanceof UploadedFile)) {
                    return;
                }

                $extension = strtolower($value->getClientOriginalExtension());
                $mime = strtolower((string) $value->getMimeType());
                $mimeOk = false;

                foreach ($accepted['mimes'] as $pattern) {
                    if (fnmatch($pattern, $mime)) {
                        $mimeOk = true;
                    }
                }

                if (! in_array($extension, $accepted['extensions'], true) && ! $mimeOk) {
                    $fail(__('validation.mimes', ['attribute' => $attribute, 'values' => implode(', ', [...$accepted['extensions'], ...$accepted['mimes']])]));
                }
            };
        }

        // Livewire hands over stored paths; the browser and the JSON API hand
        // over files. A path must be one of this form's own uploads.
        $rules[] = function (string $attribute, mixed $value, \Closure $fail): void {
            if ($value instanceof UploadedFile) {
                return;
            }

            /** @var Form|null $form */
            $form = app()->bound('packstub-form-builder.current-form') ? app('packstub-form-builder.current-form') : null;

            if (! is_string($value) || $form === null || ! Uploads::owns($form, $value) || ! $this->exists($value)) {
                $fail(__('validation.file', ['attribute' => $attribute]));
            }
        };

        return $rules;
    }

    /**
     * Uploaded files become stored paths; paths (the Livewire renderer
     * stores on upload) stay; data URIs (the JSON API) are decoded.
     */
    public function normalize(mixed $value, Field $field): mixed
    {
        if ($value === null || $value === '') {
            return [];
        }

        /** @var Form|null $form */
        $form = app()->bound('packstub-form-builder.current-form') ? app('packstub-form-builder.current-form') : null;
        $paths = [];

        foreach (array_values((array) $value) as $item) {
            if ($item instanceof TemporaryUploadedFile && $form !== null) {
                $paths[] = Uploads::store($item, $form);
            } elseif ($item instanceof UploadedFile && $form !== null) {
                $paths[] = Uploads::store($item, $form);
            } elseif (is_array($item) && is_string($item['data'] ?? null) && $form !== null) {
                $path = Uploads::storeDataUri($item['data'], $form, is_string($item['name'] ?? null) ? $item['name'] : null);

                if ($path !== null) {
                    $paths[] = $path;
                }
            } elseif (is_string($item) && str_starts_with($item, 'data:') && $form !== null) {
                $path = Uploads::storeDataUri($item, $form);

                if ($path !== null) {
                    $paths[] = $path;
                }
            } elseif (is_string($item) && $form !== null && Uploads::owns($form, $item)) {
                $paths[] = $item;
            }
        }

        return $paths;
    }

    public function format(mixed $value, Field $field): string
    {
        return implode(', ', array_map(fn (string $path): string => Uploads::originalName($path), Uploads::files($value)));
    }

    public function display(mixed $value, Field $field): string|HtmlString
    {
        return $this->format($value, $field);
    }

    /**
     * Before validation the JSON API's {name, data} items are plain arrays;
     * make them files so the size and type rules apply.
     */
    public function prepare(mixed $value, Field $field): mixed
    {
        if ($value === null || $value === '') {
            return null;
        }

        $items = [];

        foreach (array_values((array) $value) as $item) {
            $dataUri = is_array($item) ? ($item['data'] ?? null) : $item;
            $name = is_array($item) ? ($item['name'] ?? null) : null;

            if (is_string($dataUri) && str_starts_with($dataUri, 'data:') && preg_match('~^data:([\w/.+-]+);base64,(.+)$~s', $dataUri, $matches)) {
                $contents = base64_decode($matches[2], true);

                if ($contents === false) {
                    $items[] = $item;

                    continue;
                }

                $temp = tempnam(sys_get_temp_dir(), 'fb-upload');
                file_put_contents($temp, $contents);
                app()->terminating(fn () => @unlink($temp));
                $items[] = new UploadedFile($temp, is_string($name) && $name !== '' ? $name : 'upload', $matches[1], null, true);

                continue;
            }

            $items[] = $item;
        }

        return $items;
    }

    public function formComponent(Field $field): Component
    {
        /** @var Form|null $form */
        $form = app()->bound('packstub-form-builder.current-form') ? app('packstub-form-builder.current-form') : null;

        $upload = FileUpload::make($field->key)
            ->disk(Uploads::disk())
            ->directory($form === null ? trim((string) config('packstub-form-builder.uploads.directory', 'form-builder'), '/') : Uploads::directory($form))
            ->visibility('private')
            ->maxSize($this->maxKb($field))
            ->getUploadedFileNameForStorageUsing(fn (TemporaryUploadedFile $file): string => (string) Str::ulid().Uploads::SEPARATOR.Str::limit(Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)) ?: 'file', 60, '').'.'.strtolower($file->getClientOriginalExtension() ?: 'bin'))
            ->previewable(false);

        if ($this->isMultiple($field)) {
            $upload->multiple()->maxFiles((int) ($field->option('max_files') ?: 5));
        }

        $accepted = $this->accepted($field);

        if ($accepted['mimes'] !== []) {
            $upload->acceptedFileTypes($accepted['mimes']);
        }

        return $this->configure($upload, $field);
    }

    public function exists(string $path): bool
    {
        return Storage::disk(Uploads::disk())->exists($path);
    }
}
