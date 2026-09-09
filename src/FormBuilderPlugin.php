<?php

namespace Packstub\FormBuilder;

use Closure;
use Filament\Contracts\Plugin;
use Filament\Facades\Filament;
use Filament\Panel;
use Illuminate\Support\Facades\Gate;
use Packstub\FormBuilder\Fields\FieldType;
use Packstub\FormBuilder\Fields\FieldTypeRegistry;
use Packstub\FormBuilder\Filament\Resources\FormResource;
use Packstub\FormBuilder\Models\Form;

class FormBuilderPlugin implements Plugin
{
    public const ID = 'packstub-form-builder';

    /** @var array<int, class-string<FieldType>|FieldType> */
    protected array $fieldTypes = [];

    /** @var array<int, class-string<FieldType>|string> */
    protected array $withoutFieldTypes = [];

    /** @var class-string<FormResource> */
    protected string $resource = FormResource::class;

    protected bool $hasResource = true;

    protected ?string $navigationGroup = null;

    protected ?string $navigationIcon = null;

    protected ?int $navigationSort = null;

    protected ?bool $navigationBadge = null;

    /** @var (Closure(): bool)|null */
    protected ?Closure $authorize = null;

    public static function make(): static
    {
        return app(static::class);
    }

    public static function get(): static
    {
        /** @var static $plugin */
        $plugin = filament(static::ID);

        return $plugin;
    }

    public function getId(): string
    {
        return static::ID;
    }

    // ------------------------------------------------------------------
    // Configuration
    // ------------------------------------------------------------------

    /** @param array<int, class-string<FieldType>|FieldType> $types */
    public function fieldTypes(array $types): static
    {
        $this->fieldTypes = [...$this->fieldTypes, ...$types];

        return $this;
    }

    /**
     * Hide built-in field types from the builder.
     *
     * @param  array<int, class-string<FieldType>|string>  $types  Classes or ids.
     */
    public function withoutFieldTypes(array $types): static
    {
        $this->withoutFieldTypes = [...$this->withoutFieldTypes, ...$types];

        return $this;
    }

    /** @param class-string<FormResource> $resource */
    public function resource(string $resource): static
    {
        $this->resource = $resource;

        return $this;
    }

    /**
     * Skip registering the Forms resource (bring your own).
     */
    public function withoutResource(): static
    {
        $this->hasResource = false;

        return $this;
    }

    /**
     * Who may see and manage forms. Runs in addition to any policy on the
     * Form model and the packstub-form-builder.gate ability.
     *
     * @param  Closure(): bool  $callback
     */
    public function authorize(Closure $callback): static
    {
        $this->authorize = $callback;

        return $this;
    }

    public function isAuthorized(): bool
    {
        if ($this->authorize !== null && ! (bool) app()->call($this->authorize)) {
            return false;
        }

        $gate = config('packstub-form-builder.gate');

        return ! $gate || Gate::allows($gate);
    }

    public function navigationGroup(?string $group): static
    {
        $this->navigationGroup = $group;

        return $this;
    }

    public function navigationIcon(?string $icon): static
    {
        $this->navigationIcon = $icon;

        return $this;
    }

    public function navigationSort(?int $sort): static
    {
        $this->navigationSort = $sort;

        return $this;
    }

    /**
     * Show the unread submissions count on the navigation item.
     */
    public function navigationBadge(bool $condition = true): static
    {
        $this->navigationBadge = $condition;

        return $this;
    }

    // ------------------------------------------------------------------
    // Accessors
    // ------------------------------------------------------------------

    public function getNavigationGroup(): ?string
    {
        return $this->navigationGroup ?? config('packstub-form-builder.navigation.group');
    }

    public function getNavigationIcon(): string
    {
        return $this->navigationIcon ?? config('packstub-form-builder.navigation.icon', 'heroicon-o-document-text');
    }

    public function getNavigationSort(): ?int
    {
        return $this->navigationSort ?? config('packstub-form-builder.navigation.sort');
    }

    public function hasNavigationBadge(): bool
    {
        return $this->navigationBadge ?? (bool) config('packstub-form-builder.navigation.badge', true);
    }

    /** @return class-string<FormResource> */
    public function getResource(): string
    {
        return $this->resource;
    }

    /**
     * The panel URL of a form's submissions, or null outside a panel that
     * runs the plugin.
     */
    public static function submissionsUrl(Form $form): ?string
    {
        foreach (Filament::getPanels() as $panel) {
            if (! $panel->hasPlugin(static::ID)) {
                continue;
            }

            /** @var static $plugin */
            $plugin = $panel->getPlugin(static::ID);

            return $plugin->getResource()::getUrl('edit', ['record' => $form], panel: $panel->getId()).'#submissions';
        }

        return null;
    }

    // ------------------------------------------------------------------
    // Panel integration
    // ------------------------------------------------------------------

    public function register(Panel $panel): void
    {
        app(FieldTypeRegistry::class)
            ->register($this->fieldTypes)
            ->forget($this->withoutFieldTypes);

        if ($this->hasResource) {
            $panel->resources([$this->resource]);
        }
    }

    public function boot(Panel $panel): void
    {
        //
    }
}
