<?php

namespace Packstub\FormBuilder;

use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Packstub\FormBuilder\Contracts\ChoiceSource;
use Packstub\FormBuilder\Contracts\SubmissionSink;
use Packstub\FormBuilder\Fields\ChoiceSources;
use Packstub\FormBuilder\Fields\FieldType;
use Packstub\FormBuilder\Fields\FieldTypeRegistry;
use Packstub\FormBuilder\Models\Form;
use Packstub\FormBuilder\Models\FormSubmission;
use Packstub\FormBuilder\Models\ShareLink;
use Packstub\FormBuilder\Models\WebhookDelivery;
use Packstub\FormBuilder\Submissions\ProtectionToken;
use Packstub\FormBuilder\Submissions\SubmissionContext;
use Packstub\FormBuilder\Submissions\SubmissionResult;
use Packstub\FormBuilder\Submissions\Submitter;
use Packstub\FormBuilder\Testing\FormBuilderFake;

class FormBuilder
{
    /** @var array<int, class-string<SubmissionSink>|SubmissionSink> */
    protected array $sinks = [];

    public function __construct(protected FieldTypeRegistry $types) {}

    /** @return class-string<Form> */
    public static function formModel(): string
    {
        return config('packstub-form-builder.models.form', Form::class);
    }

    /** @return class-string<FormSubmission> */
    public static function submissionModel(): string
    {
        return config('packstub-form-builder.models.submission', FormSubmission::class);
    }

    /** @return class-string<WebhookDelivery> */
    public static function webhookDeliveryModel(): string
    {
        return config('packstub-form-builder.models.webhook_delivery', WebhookDelivery::class);
    }

    /** @return class-string<ShareLink> */
    public static function shareLinkModel(): string
    {
        return config('packstub-form-builder.models.share_link', ShareLink::class);
    }

    // ------------------------------------------------------------------
    // Ownership
    // ------------------------------------------------------------------

    /**
     * The user whose forms the Forms resource is limited to (config
     * "ownership.only_own"), or null when everyone sees every form.
     */
    public static function restrictedToOwner(): int|string|null
    {
        if (! config('packstub-form-builder.ownership.only_own', false)) {
            return null;
        }

        $user = auth()->user();

        if ($user === null) {
            return null;
        }

        $ability = config('packstub-form-builder.ownership.see_all');

        if (is_string($ability) && $ability !== '' && Gate::forUser($user)->allows($ability)) {
            return null;
        }

        return $user->getAuthIdentifier();
    }

    // ------------------------------------------------------------------
    // Tenancy
    // ------------------------------------------------------------------

    /**
     * The column on the forms table that holds the tenant key, or null when
     * the plugin is not tenant-aware (config "tenancy.enabled").
     */
    public static function tenantColumn(): ?string
    {
        if (! config('packstub-form-builder.tenancy.enabled', false)) {
            return null;
        }

        return (string) config('packstub-form-builder.tenancy.column', 'tenant_id');
    }

    /** @return class-string<Model>|null */
    public static function tenantModel(): ?string
    {
        $model = config('packstub-form-builder.tenancy.model');

        return is_string($model) && $model !== '' ? $model : null;
    }

    /**
     * The key of the current tenant: the one resolved by config
     * "tenancy.resolver", else Filament's current tenant, else null.
     */
    public static function currentTenantKey(): int|string|null
    {
        if (static::tenantColumn() === null) {
            return null;
        }

        $resolver = config('packstub-form-builder.tenancy.resolver');

        if (is_callable($resolver)) {
            $tenant = $resolver();
        } elseif (class_exists(Filament::class)) {
            $tenant = Filament::getTenant();
        } else {
            $tenant = null;
        }

        if ($tenant instanceof Model) {
            return $tenant->getKey();
        }

        return is_int($tenant) || is_string($tenant) ? $tenant : null;
    }

    public function fieldTypes(): FieldTypeRegistry
    {
        return $this->types;
    }

    /**
     * @param  array<int, class-string<FieldType>|FieldType>  $types
     */
    public function registerFieldTypes(array $types): static
    {
        $this->types->register($types);

        return $this;
    }

    /**
     * Register a choice source: choice fields can take their options from it
     * (the builder's "Options" select) instead of a typed list.
     *
     * @param  \Closure|ChoiceSource|array<int|string, string>|class-string<ChoiceSource>  $source  A closure receives the Field and returns value => label.
     */
    public function choices(string $name, \Closure|ChoiceSource|array|string $source, ?string $label = null): static
    {
        app(ChoiceSources::class)->add($name, $source, $label);

        return $this;
    }

    public function choiceSources(): ChoiceSources
    {
        return app(ChoiceSources::class);
    }

    /**
     * @param  array<int, class-string<SubmissionSink>|SubmissionSink>  $sinks
     */
    public function sink(array|string|SubmissionSink $sinks): static
    {
        foreach (is_array($sinks) ? $sinks : [$sinks] as $sink) {
            $this->sinks[] = $sink;
        }

        return $this;
    }

    /**
     * @return array<int, SubmissionSink>
     */
    public function sinks(): array
    {
        return array_map(
            fn (string|SubmissionSink $sink): SubmissionSink => $sink instanceof SubmissionSink ? $sink : app($sink),
            [...(array) config('packstub-form-builder.sinks', []), ...$this->sinks],
        );
    }

    public function forgetSinks(): static
    {
        $this->sinks = [];

        return $this;
    }

    /**
     * Find a form by slug or id.
     */
    public function find(Form|string|int $form): ?Form
    {
        if ($form instanceof Form) {
            return $form;
        }

        $model = static::formModel();

        return is_int($form) || ctype_digit((string) $form)
            ? $model::query()->find($form)
            : $model::query()->where('slug', $form)->first();
    }

    /**
     * Input that passes the time trap and the token check for the form, with
     * your values: post it in a test (the honeypot stays empty).
     *
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    public function validInput(Form|string|int $form, array $values = []): array
    {
        $form = $this->find($form) ?? throw new \InvalidArgumentException('Unknown form.');
        $tokens = app(ProtectionToken::class);

        return [...$values, $tokens->field() => $tokens->make($form)];
    }

    /**
     * Whether FormBuilder::fake() is on: the side effects of a submission
     * (emails, notifications, webhooks, channel messages, sinks) are skipped.
     */
    public static function faking(): bool
    {
        return app(self::class) instanceof FormBuilderFake;
    }

    /**
     * Submit from code, bypassing the spam checks that need a rendered form.
     *
     * @param  array<string, mixed>  $data
     */
    public function submit(Form|string|int $form, array $data, ?SubmissionContext $context = null): SubmissionResult
    {
        $form = $this->find($form) ?? throw new \InvalidArgumentException('Unknown form.');
        $context ??= new SubmissionContext(channel: 'code');

        return app(Submitter::class)->submit($form, $data, $context->trusted());
    }
}
