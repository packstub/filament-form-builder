<?php

namespace Packstub\FormBuilder;

use Filament\Facades\Filament;
use Filament\Models\Contracts\HasTenants;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
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

    /** @var (\Closure(Builder<Model>, ?Model): (Builder<Model>|null))|null */
    protected ?\Closure $panelUsersQuery = null;

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

    /**
     * The tenant model: config "tenancy.model", else the one of the panel
     * that runs the plugin (a panel with ->tenant()).
     *
     * @return class-string<Model>|null
     */
    public static function tenantModel(): ?string
    {
        $model = config('packstub-form-builder.tenancy.model');

        if (is_string($model) && $model !== '') {
            return $model;
        }

        if (static::tenantColumn() === null || ! class_exists(Filament::class)) {
            return null;
        }

        foreach (Filament::getPanels() as $panel) {
            if ($panel->hasPlugin(FormBuilderPlugin::ID) && $panel->hasTenancy()) {
                return $panel->getTenantModel();
            }
        }

        return null;
    }

    /**
     * The tenant a form belongs to, or null: outside tenancy, for a form
     * without one, or when the tenant model is not known.
     */
    public static function tenantOf(Form $form): ?Model
    {
        $column = static::tenantColumn();
        $model = static::tenantModel();
        $key = $column !== null ? $form->getAttribute($column) : null;

        if ($model === null || $key === null) {
            return null;
        }

        return $model::query()->find($key);
    }

    /**
     * The key of the current tenant: the one resolved by config
     * "tenancy.resolver", else Filament's current tenant, else null.
     */
    public static function currentTenantKey(): int|string|null
    {
        $tenant = static::resolveCurrentTenant();

        if ($tenant instanceof Model) {
            return $tenant->getKey();
        }

        return is_int($tenant) || is_string($tenant) ? $tenant : null;
    }

    /**
     * The current tenant as a model, or null: outside a tenant, or when the
     * resolver returns a key and the tenant model is not known.
     */
    public static function currentTenant(): ?Model
    {
        $tenant = static::resolveCurrentTenant();

        if ($tenant instanceof Model || $tenant === null) {
            return $tenant;
        }

        $model = static::tenantModel();

        return $model !== null && (is_int($tenant) || is_string($tenant)) ? $model::query()->find($tenant) : null;
    }

    protected static function resolveCurrentTenant(): mixed
    {
        if (static::tenantColumn() === null) {
            return null;
        }

        $resolver = config('packstub-form-builder.tenancy.resolver');

        if (is_callable($resolver)) {
            return $resolver();
        }

        return class_exists(Filament::class) ? Filament::getTenant() : null;
    }

    /**
     * Whether forms may carry their own CSS (config "frontend.custom_css").
     */
    public static function allowsCustomCss(): bool
    {
        return (bool) config('packstub-form-builder.frontend.custom_css', true);
    }

    /**
     * Whether a form may set its own sender address (config
     * "notifications.custom_from"), or when it is null (the default), only
     * outside tenancy: a customer must not send as any address on your
     * mailer.
     */
    public static function allowsCustomFrom(): bool
    {
        $allowed = config('packstub-form-builder.notifications.custom_from');

        return $allowed === null ? static::tenantColumn() === null : (bool) $allowed;
    }

    /**
     * Whether forms may carry their own JavaScript: config
     * "frontend.custom_js", or when it is null (the default), only outside
     * tenancy, where the people who build forms are the site's own.
     */
    public static function allowsCustomJs(): bool
    {
        $allowed = config('packstub-form-builder.frontend.custom_js');

        return $allowed === null ? static::tenantColumn() === null : (bool) $allowed;
    }

    // ------------------------------------------------------------------
    // Panel users
    // ------------------------------------------------------------------

    /**
     * Narrow or order the users a form can notify in the panel (the "Notify
     * in the panel" picker). The callback receives the query on the user
     * model and the current tenant (null outside tenancy) and returns the
     * query. In a tenant, members are narrowed on top of it, see panelUsers().
     *
     * @param  (\Closure(Builder<Model>, ?Model): (Builder<Model>|null))|null  $callback
     */
    public function panelUsersUsing(?\Closure $callback): static
    {
        $this->panelUsersQuery = $callback;

        return $this;
    }

    /**
     * How many users panelUsers() asks one by one, at most, when the tenant
     * has no members relationship.
     */
    public const PANEL_USERS_SCAN_LIMIT = 2000;

    /**
     * The users a form can notify in the panel. In a tenant, only its
     * members: the users of its members relationship (config
     * "tenancy.members", else a "members" or "users" relationship to the
     * user model), in one query. Without one, the users whose
     * canAccessTenant() accepts it (Filament's HasTenants), asked one by
     * one among the first PANEL_USERS_SCAN_LIMIT users of the query. When
     * neither proves membership, nobody: a tenant's submissions never go to
     * users it can't vouch for.
     *
     * @return Collection<int, Model>
     */
    public function panelUsers(?Model $tenant = null, int $limit = 500): Collection
    {
        $model = config('auth.providers.users.model');

        if (! is_string($model) || ! class_exists($model)) {
            return new Collection;
        }

        /** @var Builder<Model> $query */
        $query = $model::query();

        if ($this->panelUsersQuery !== null) {
            $query = ($this->panelUsersQuery)($query, $tenant) ?? $query;
        }

        if ($tenant === null) {
            return $query->limit($limit)->get();
        }

        if (($members = static::membersOf($tenant, $query->getModel())) !== null) {
            return $query
                ->whereIn($query->getModel()->getQualifiedKeyName(), $members->getQuery()->select($members->getRelated()->getQualifiedKeyName()))
                ->limit($limit)
                ->get();
        }

        if (! $query->getModel() instanceof HasTenants) {
            return new Collection;
        }

        return new Collection(
            $query->reorder()->orderBy($query->getModel()->getQualifiedKeyName())
                ->limit(self::PANEL_USERS_SCAN_LIMIT)
                ->cursor()
                ->filter(fn (Model $user): bool => static::canAccessTenant($user, $tenant))
                ->take($limit)
                ->values()
                ->all(),
        );
    }

    /**
     * The tenant's relationship to its users: config "tenancy.members",
     * else "members" or "users", when it leads to the user model.
     */
    protected static function membersOf(Model $tenant, Model $user): ?Relation
    {
        $configured = config('packstub-form-builder.tenancy.members');
        $names = is_string($configured) && $configured !== '' ? [$configured] : ['members', 'users'];

        foreach ($names as $name) {
            if (! method_exists($tenant, $name)) {
                continue;
            }

            $relation = $tenant->{$name}();

            if ($relation instanceof Relation && $relation->getRelated() instanceof $user) {
                return $relation;
            }
        }

        return null;
    }

    /**
     * Whether a user may be told about a tenant's submissions: a member
     * through the tenant's members relationship (see panelUsers()), else
     * one whose canAccessTenant() accepts it, else nobody.
     */
    public static function canAccessTenant(Model $user, Model $tenant): bool
    {
        if (($members = static::membersOf($tenant, $user)) !== null) {
            return $members->getQuery()->whereKey($user->getKey())->exists();
        }

        return $user instanceof HasTenants && $user->canAccessTenant($tenant);
    }

    /**
     * The users "Notify in the panel" offers where the form is edited: the
     * current tenant's members, nobody when a tenant is current but can't
     * be loaded as a model (a resolver returning a key without
     * "tenancy.model"), everyone the query allows outside tenancy.
     *
     * @return Collection<int, Model>
     */
    public function currentPanelUsers(int $limit = 500): Collection
    {
        $tenant = static::currentTenant();

        if ($tenant === null && static::currentTenantKey() !== null) {
            return new Collection;
        }

        return $this->panelUsers($tenant, $limit);
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
