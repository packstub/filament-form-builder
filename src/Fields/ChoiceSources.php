<?php

namespace Packstub\FormBuilder\Fields;

use Closure;
use Illuminate\Support\Str;
use Packstub\FormBuilder\Contracts\ChoiceSource;

/**
 * The named choice sources a choice field can take its options from:
 * closures, arrays or ChoiceSource classes registered in code. Results are
 * kept for the rest of the request, per source and field.
 */
class ChoiceSources
{
    /** @var array<string, array{source: Closure|ChoiceSource|array<int|string, string>|class-string<ChoiceSource>, label: ?string}> */
    protected array $sources = [];

    /** @var array<string, array<string, string>> */
    protected array $resolved = [];

    /**
     * @param  Closure|ChoiceSource|array<int|string, string>|class-string<ChoiceSource>  $source  A closure receives the Field and returns value => label.
     */
    public function add(string $name, Closure|ChoiceSource|array|string $source, ?string $label = null): static
    {
        $this->sources[$name] = ['source' => $source, 'label' => $label];
        $this->flush();

        return $this;
    }

    /**
     * @param  array<string, ChoiceSource|array<int|string, string>|class-string<ChoiceSource>>  $sources
     */
    public function register(array $sources): static
    {
        foreach ($sources as $name => $source) {
            $this->add((string) $name, $source);
        }

        return $this;
    }

    public function has(string $name): bool
    {
        return isset($this->sources[$name]);
    }

    /**
     * name => label, for the builder.
     *
     * @return array<string, string>
     */
    public function options(): array
    {
        $options = [];

        foreach ($this->sources as $name => $entry) {
            $source = $this->instance($entry['source']);
            $options[$name] = $entry['label'] ?? ($source instanceof ChoiceSource ? $source->label() : Str::headline($name));
        }

        return $options;
    }

    /**
     * The choices of a source for a field, value => label; empty when the
     * source is not registered.
     *
     * @return array<string, string>
     */
    public function resolve(string $name, Field $field): array
    {
        if (! isset($this->sources[$name])) {
            return [];
        }

        $cacheKey = $name.'|'.$field->key;

        if (isset($this->resolved[$cacheKey])) {
            return $this->resolved[$cacheKey];
        }

        $source = $this->instance($this->sources[$name]['source']);
        $choices = match (true) {
            $source instanceof ChoiceSource => $source->choices($field),
            $source instanceof Closure => $source($field),
            default => $source,
        };

        $normalized = [];

        foreach (is_iterable($choices) ? $choices : [] as $value => $label) {
            if ($value === '' || (! is_scalar($label) && ! $label instanceof \Stringable)) {
                continue;
            }

            $normalized[(string) $value] = (string) $label;
        }

        return $this->resolved[$cacheKey] = $normalized;
    }

    /**
     * Forget the resolved choices (they are kept for the rest of the request).
     */
    public function flush(): static
    {
        $this->resolved = [];

        return $this;
    }

    /**
     * @param  Closure|ChoiceSource|array<int|string, string>|class-string<ChoiceSource>  $source
     * @return Closure|ChoiceSource|array<int|string, string>
     */
    protected function instance(Closure|ChoiceSource|array|string $source): Closure|ChoiceSource|array
    {
        if (is_string($source)) {
            $source = app($source);
        }

        return $source;
    }
}
