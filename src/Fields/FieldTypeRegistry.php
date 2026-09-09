<?php

namespace Packstub\FormBuilder\Fields;

use Illuminate\Support\Collection;
use InvalidArgumentException;

class FieldTypeRegistry
{
    /** @var array<string, FieldType> */
    protected array $types = [];

    /**
     * @param  array<int, class-string<FieldType>|FieldType>  $types
     */
    public function register(array $types): static
    {
        foreach ($types as $type) {
            $instance = $type instanceof FieldType ? $type : $this->instantiate($type);

            $this->types[$instance::id()] = $instance;
        }

        return $this;
    }

    /**
     * @param  array<int, class-string<FieldType>|string>  $types  Classes or ids.
     */
    public function forget(array $types): static
    {
        foreach ($types as $type) {
            $id = is_subclass_of($type, FieldType::class) ? $type::id() : (string) $type;

            unset($this->types[$id]);
        }

        return $this;
    }

    /**
     * @return Collection<string, FieldType>
     */
    public function all(): Collection
    {
        return collect($this->types);
    }

    public function find(string $id): ?FieldType
    {
        return $this->types[$id] ?? null;
    }

    public function get(string $id): FieldType
    {
        return $this->find($id) ?? throw new InvalidArgumentException("Unknown form field type [{$id}].");
    }

    public function has(string $id): bool
    {
        return isset($this->types[$id]);
    }

    protected function instantiate(string $class): FieldType
    {
        if (! is_subclass_of($class, FieldType::class)) {
            throw new InvalidArgumentException("[{$class}] is not a ".FieldType::class.'.');
        }

        return app($class);
    }
}
