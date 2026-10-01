<?php

namespace Packstub\FormBuilder\Filament\Resources;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontFamily;
use Filament\Support\Enums\IconPosition;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Packstub\FormBuilder\Filament\FieldBlocks;
use Packstub\FormBuilder\Filament\FormActions;
use Packstub\FormBuilder\Filament\Resources\FormResource\Pages;
use Packstub\FormBuilder\Filament\Resources\FormResource\RelationManagers\ShareLinksRelationManager;
use Packstub\FormBuilder\Filament\Resources\FormResource\RelationManagers\SubmissionsRelationManager;
use Packstub\FormBuilder\Filament\Resources\FormResource\RelationManagers\WebhookDeliveriesRelationManager;
use Packstub\FormBuilder\Filament\Widgets\SubmissionsChart;
use Packstub\FormBuilder\FormBuilder;
use Packstub\FormBuilder\FormBuilderPlugin;
use Packstub\FormBuilder\Models\Form;
use Packstub\FormBuilder\Notifications\ChannelMessage;
use Packstub\FormBuilder\Submissions\Captcha;
use Packstub\FormBuilder\Webhooks\Webhook;

class FormResource extends Resource
{
    protected static ?string $slug = 'forms';

    protected static ?string $recordTitleAttribute = 'name';

    // Tenant scoping is the model's own global scope (config "tenancy").
    protected static bool $isScopedToTenant = false;

    public static function getModel(): string
    {
        return FormBuilder::formModel();
    }

    public static function getModelLabel(): string
    {
        return __('packstub-form-builder::form-builder.resource.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('packstub-form-builder::form-builder.resource.plural');
    }

    public static function getNavigationLabel(): string
    {
        return __('packstub-form-builder::form-builder.resource.navigation');
    }

    public static function getNavigationGroup(): ?string
    {
        return static::plugin()?->getNavigationGroup();
    }

    public static function getNavigationIcon(): string|BackedEnum|null
    {
        return static::plugin()?->getNavigationIcon() ?? 'heroicon-o-document-text';
    }

    public static function getNavigationSort(): ?int
    {
        return static::plugin()?->getNavigationSort();
    }

    public static function getNavigationBadge(): ?string
    {
        if (! (static::plugin()?->hasNavigationBadge() ?? true)) {
            return null;
        }

        $unread = FormBuilder::submissionModel()::query()
            ->unread()
            ->whereHas('form', fn (Builder $query) => static::scopeToOwner($query))
            ->count();

        return $unread > 0 ? (string) $unread : null;
    }

    public static function getEloquentQuery(): Builder
    {
        return static::scopeToOwner(parent::getEloquentQuery());
    }

    /**
     * Only the current user's forms, when config "ownership.only_own" says so.
     */
    public static function scopeToOwner(Builder $query): Builder
    {
        $owner = FormBuilder::restrictedToOwner();

        return $owner === null ? $query : $query->where($query->qualifyColumn('user_id'), $owner);
    }

    public static function canAccess(): bool
    {
        return (static::plugin()?->isAuthorized() ?? true) && parent::canAccess();
    }

    protected static function plugin(): ?FormBuilderPlugin
    {
        try {
            return FormBuilderPlugin::get();
        } catch (\Throwable) {
            return null;
        }
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make()
                    ->tabs([
                        Tab::make(__('packstub-form-builder::form-builder.tabs.fields'))
                            ->icon('heroicon-o-list-bullet')
                            ->schema([FieldBlocks::make('fields')]),
                        Tab::make(__('packstub-form-builder::form-builder.tabs.settings'))
                            ->icon('heroicon-o-cog-6-tooth')
                            ->schema(static::settingsSchema()),
                        Tab::make(__('packstub-form-builder::form-builder.tabs.notifications'))
                            ->icon('heroicon-o-bell')
                            ->schema(static::notificationsSchema()),
                        Tab::make(__('packstub-form-builder::form-builder.tabs.design'))
                            ->icon('heroicon-o-paint-brush')
                            ->schema(static::designSchema()),
                        Tab::make(__('packstub-form-builder::form-builder.tabs.embed'))
                            ->icon('heroicon-o-code-bracket')
                            ->schema(static::embedSchema())
                            ->hidden(fn (?Model $record): bool => $record === null),
                    ])
                    ->persistTabInQueryString()
                    ->columnSpanFull(),
            ])
            ->columns(1);
    }

    /**
     * @return array<int, Component>
     */
    protected static function settingsSchema(): array
    {
        $captchas = [];

        foreach (Captcha::PROVIDERS as $provider) {
            if (Captcha::isConfigured($provider)) {
                $captchas[$provider] = Str::headline($provider);
            }
        }

        return [
            Section::make(__('packstub-form-builder::form-builder.sections.general'))
                ->columns(2)
                ->schema([
                    TextInput::make('name')
                        ->label(__('packstub-form-builder::form-builder.fields.name'))
                        ->required()
                        ->maxLength(255)
                        ->live(onBlur: true)
                        ->afterStateUpdated(function (?string $state, Get $get, Set $set, ?Model $record): void {
                            if ($record === null && blank($get('slug'))) {
                                $set('slug', Str::slug((string) $state));
                            }
                        }),
                    TextInput::make('slug')
                        ->label(__('packstub-form-builder::form-builder.fields.slug'))
                        ->helperText(__('packstub-form-builder::form-builder.fields.slug_hint'))
                        ->maxLength(255)
                        ->alphaDash()
                        ->unique(ignoreRecord: true),
                    Textarea::make('description')
                        ->label(__('packstub-form-builder::form-builder.fields.description'))
                        ->rows(2)
                        ->columnSpanFull(),
                    Toggle::make('is_active')
                        ->label(__('packstub-form-builder::form-builder.fields.is_active'))
                        ->helperText(__('packstub-form-builder::form-builder.fields.is_active_hint'))
                        ->default(true),
                    Toggle::make('store_submissions')
                        ->label(__('packstub-form-builder::form-builder.fields.store_submissions'))
                        ->helperText(__('packstub-form-builder::form-builder.fields.store_submissions_hint'))
                        ->default(true),
                ]),
            Section::make(__('packstub-form-builder::form-builder.sections.after_submit'))
                ->columns(2)
                ->schema([
                    TextInput::make('submit_label')
                        ->label(__('packstub-form-builder::form-builder.fields.submit_label'))
                        ->placeholder(__('packstub-form-builder::form-builder.frontend.submit'))
                        ->maxLength(100),
                    TextInput::make('redirect_url')
                        ->label(__('packstub-form-builder::form-builder.fields.redirect_url'))
                        ->helperText(__('packstub-form-builder::form-builder.fields.redirect_url_hint'))
                        ->maxLength(2048),
                    Textarea::make('success_message')
                        ->label(__('packstub-form-builder::form-builder.fields.success_message'))
                        ->placeholder(__('packstub-form-builder::form-builder.frontend.success'))
                        ->rows(2)
                        ->columnSpanFull(),
                ]),
            Section::make(__('packstub-form-builder::form-builder.sections.availability'))
                ->columns(2)
                ->collapsed()
                ->schema([
                    DateTimePicker::make('opens_at')
                        ->label(__('packstub-form-builder::form-builder.fields.opens_at'))
                        ->native(),
                    DateTimePicker::make('closes_at')
                        ->label(__('packstub-form-builder::form-builder.fields.closes_at'))
                        ->native()
                        ->after('opens_at'),
                    TextInput::make('settings.closed_message')
                        ->label(__('packstub-form-builder::form-builder.fields.closed_message'))
                        ->placeholder(__('packstub-form-builder::form-builder.frontend.closed'))
                        ->maxLength(255)
                        ->columnSpanFull(),
                ]),
            Section::make(__('packstub-form-builder::form-builder.sections.access'))
                ->columns(2)
                ->collapsed()
                ->schema([
                    Select::make('settings.visibility')
                        ->label(__('packstub-form-builder::form-builder.fields.visibility'))
                        ->options([
                            'public' => __('packstub-form-builder::form-builder.fields.visibility_public'),
                            'private' => __('packstub-form-builder::form-builder.fields.visibility_private'),
                        ])
                        ->default('public')
                        ->formatStateUsing(fn (?string $state): string => $state ?: 'public')
                        ->selectablePlaceholder(false)
                        ->native(false),
                    TextInput::make('settings.password')
                        ->label(__('packstub-form-builder::form-builder.fields.password'))
                        ->helperText(__('packstub-form-builder::form-builder.fields.password_hint'))
                        ->maxLength(100)
                        ->autocomplete('off'),
                    Toggle::make('settings.require_login')
                        ->label(__('packstub-form-builder::form-builder.fields.require_login'))
                        ->default(false),
                    Toggle::make('settings.prefill')
                        ->label(__('packstub-form-builder::form-builder.fields.prefill'))
                        ->helperText(__('packstub-form-builder::form-builder.fields.prefill_hint'))
                        ->default((bool) config('packstub-form-builder.frontend.prefill', true)),
                ]),
            Section::make(__('packstub-form-builder::form-builder.sections.limits'))
                ->columns(2)
                ->collapsed()
                ->schema([
                    Toggle::make('settings.one_per_person')
                        ->label(__('packstub-form-builder::form-builder.fields.one_per_person'))
                        ->helperText(__('packstub-form-builder::form-builder.fields.one_per_person_hint'))
                        ->live()
                        ->default(false),
                    TextInput::make('settings.max_submissions')
                        ->label(__('packstub-form-builder::form-builder.fields.max_submissions'))
                        ->helperText(__('packstub-form-builder::form-builder.fields.max_submissions_hint'))
                        ->integer()
                        ->minValue(1)
                        ->live(onBlur: true),
                    TextInput::make('settings.already_submitted_message')
                        ->label(__('packstub-form-builder::form-builder.fields.already_submitted_message'))
                        ->placeholder(__('packstub-form-builder::form-builder.frontend.already_submitted'))
                        ->maxLength(255)
                        ->visible(fn (Get $get): bool => (bool) $get('settings.one_per_person')),
                    TextInput::make('settings.full_message')
                        ->label(__('packstub-form-builder::form-builder.fields.full_message'))
                        ->placeholder(__('packstub-form-builder::form-builder.frontend.full'))
                        ->maxLength(255)
                        ->visible(fn (Get $get): bool => filled($get('settings.max_submissions'))),
                    TextInput::make('settings.retention_days')
                        ->label(__('packstub-form-builder::form-builder.fields.retention_days'))
                        ->helperText(__('packstub-form-builder::form-builder.fields.retention_days_hint'))
                        ->integer()
                        ->minValue(1),
                ]),
            Section::make(__('packstub-form-builder::form-builder.sections.spam'))
                ->columns(2)
                ->collapsed()
                ->schema([
                    Toggle::make('settings.honeypot')
                        ->label(__('packstub-form-builder::form-builder.fields.honeypot'))
                        ->helperText(__('packstub-form-builder::form-builder.fields.honeypot_hint'))
                        ->default((bool) config('packstub-form-builder.spam.honeypot', true))
                        ->inline(false),
                    TextInput::make('settings.min_seconds')
                        ->label(__('packstub-form-builder::form-builder.fields.min_seconds'))
                        ->helperText(__('packstub-form-builder::form-builder.fields.min_seconds_hint'))
                        ->integer()
                        ->minValue(0)
                        ->maxValue(600)
                        ->default((int) config('packstub-form-builder.spam.min_seconds', 2)),
                    Select::make('settings.captcha')
                        ->label(__('packstub-form-builder::form-builder.fields.captcha'))
                        ->helperText(__('packstub-form-builder::form-builder.fields.captcha_hint'))
                        ->options(['' => __('packstub-form-builder::form-builder.fields.captcha_default'), 'none' => __('packstub-form-builder::form-builder.fields.captcha_none'), ...$captchas])
                        ->placeholder(__('packstub-form-builder::form-builder.fields.captcha_default'))
                        ->native(false),
                ]),
        ];
    }

    /**
     * @return array<int, Component>
     */
    protected static function notificationsSchema(): array
    {
        return [
            Section::make(__('packstub-form-builder::form-builder.sections.admin_email'))
                ->columns(2)
                ->schema([
                    TagsInput::make('notification_emails')
                        ->label(__('packstub-form-builder::form-builder.fields.notification_emails'))
                        ->helperText(__('packstub-form-builder::form-builder.fields.notification_emails_hint'))
                        ->placeholder('name@example.com')
                        ->nestedRecursiveRules(['email'])
                        ->columnSpanFull(),
                    TextInput::make('settings.notify_subject')
                        ->label(__('packstub-form-builder::form-builder.fields.notify_subject'))
                        ->helperText(__('packstub-form-builder::form-builder.fields.notify_subject_hint'))
                        ->placeholder(__('packstub-form-builder::form-builder.mail.subject', ['form' => '{form_name}']))
                        ->maxLength(255)
                        ->columnSpanFull(),
                    TextInput::make('settings.notify_from_email')
                        ->label(__('packstub-form-builder::form-builder.fields.notify_from_email'))
                        ->email()
                        ->placeholder((string) (config('packstub-form-builder.notifications.from_email') ?: config('mail.from.address')))
                        ->maxLength(255),
                    TextInput::make('settings.notify_from_name')
                        ->label(__('packstub-form-builder::form-builder.fields.notify_from_name'))
                        ->placeholder((string) (config('packstub-form-builder.notifications.from_name') ?: config('mail.from.name')))
                        ->maxLength(255),
                    TextInput::make('settings.notify_reply_to')
                        ->label(__('packstub-form-builder::form-builder.fields.notify_reply_to'))
                        ->helperText(__('packstub-form-builder::form-builder.fields.notify_reply_to_hint'))
                        ->placeholder('respondent')
                        ->maxLength(255),
                    Toggle::make('settings.notify_attach_files')
                        ->label(__('packstub-form-builder::form-builder.fields.notify_attach_files'))
                        ->inline(false),
                    TagsInput::make('settings.notify_cc')
                        ->label(__('packstub-form-builder::form-builder.fields.notify_cc'))
                        ->nestedRecursiveRules(['email']),
                    TagsInput::make('settings.notify_bcc')
                        ->label(__('packstub-form-builder::form-builder.fields.notify_bcc'))
                        ->nestedRecursiveRules(['email']),
                ]),
            Section::make(__('packstub-form-builder::form-builder.sections.autoresponder'))
                ->columns(2)
                ->collapsed(fn (?Model $record): bool => ! (bool) $record?->setting('autoresponder', false))
                ->schema([
                    Toggle::make('settings.autoresponder')
                        ->label(__('packstub-form-builder::form-builder.fields.autoresponder'))
                        ->live()
                        ->columnSpanFull(),
                    Select::make('settings.autoresponder_field')
                        ->label(__('packstub-form-builder::form-builder.fields.autoresponder_field'))
                        ->options(fn (Get $get): array => static::fieldOptions((array) $get('fields'), ['email']))
                        ->native(false)
                        ->visible(fn (Get $get): bool => (bool) $get('settings.autoresponder')),
                    TextInput::make('settings.autoresponder_subject')
                        ->label(__('packstub-form-builder::form-builder.fields.autoresponder_subject'))
                        ->placeholder(__('packstub-form-builder::form-builder.mail.autoresponder_subject'))
                        ->maxLength(255)
                        ->visible(fn (Get $get): bool => (bool) $get('settings.autoresponder')),
                    Textarea::make('settings.autoresponder_body')
                        ->label(__('packstub-form-builder::form-builder.fields.autoresponder_body'))
                        ->helperText(__('packstub-form-builder::form-builder.fields.autoresponder_body_hint'))
                        ->placeholder(__('packstub-form-builder::form-builder.mail.autoresponder_body'))
                        ->rows(6)
                        ->columnSpanFull()
                        ->visible(fn (Get $get): bool => (bool) $get('settings.autoresponder')),
                    Toggle::make('settings.autoresponder_include_values')
                        ->label(__('packstub-form-builder::form-builder.submissions.data'))
                        ->visible(fn (Get $get): bool => (bool) $get('settings.autoresponder')),
                ]),
            Section::make(__('packstub-form-builder::form-builder.sections.in_app'))
                ->collapsed(fn (?Model $record): bool => blank($record?->setting('notify_users')))
                ->schema([
                    Select::make('settings.notify_users')
                        ->label(__('packstub-form-builder::form-builder.fields.notify_users'))
                        ->helperText(__('packstub-form-builder::form-builder.fields.notify_users_hint'))
                        ->options(fn (): array => static::userOptions())
                        ->multiple()
                        ->searchable()
                        ->native(false),
                ]),
            Section::make(__('packstub-form-builder::form-builder.sections.channels'))
                ->description(__('packstub-form-builder::form-builder.fields.channels_hint'))
                ->collapsed(fn (?Model $record): bool => blank($record?->setting('channels')))
                ->schema([
                    Repeater::make('settings.channels')
                        ->hiddenLabel()
                        ->schema([
                            Select::make('provider')
                                ->label(__('packstub-form-builder::form-builder.fields.channel_provider'))
                                ->options(collect(ChannelMessage::PROVIDERS)->mapWithKeys(fn (string $provider): array => [$provider => __('packstub-form-builder::form-builder.channels.'.$provider)])->all())
                                ->default('slack')
                                ->selectablePlaceholder(false)
                                ->required()
                                ->native(false),
                            TextInput::make('url')
                                ->label(__('packstub-form-builder::form-builder.fields.channel_url'))
                                ->url()
                                ->startsWith(['https://'])
                                ->required()
                                ->maxLength(2048)
                                ->columnSpan(2),
                        ])
                        ->columns(3)
                        ->compact()
                        ->defaultItems(0)
                        ->reorderable(false)
                        ->addActionLabel(__('packstub-form-builder::form-builder.fields.add_channel')),
                ]),
            Section::make(__('packstub-form-builder::form-builder.sections.webhook'))
                ->columns(2)
                ->collapsed(fn (?Model $record): bool => blank($record?->setting('webhook_url')))
                ->schema([
                    TextInput::make('settings.webhook_url')
                        ->label(__('packstub-form-builder::form-builder.fields.webhook_url'))
                        ->helperText(__('packstub-form-builder::form-builder.fields.webhook_url_hint'))
                        ->url()
                        ->maxLength(2048)
                        ->live(onBlur: true)
                        ->afterStateUpdated(function (?string $state, Get $get, Set $set): void {
                            if (filled($state) && blank($get('settings.webhook_secret'))) {
                                $set('settings.webhook_secret', Webhook::generateSecret());
                            }
                        }),
                    Select::make('settings.webhook_method')
                        ->label(__('packstub-form-builder::form-builder.fields.webhook_method'))
                        ->options(['POST' => 'POST', 'PUT' => 'PUT', 'PATCH' => 'PATCH'])
                        ->default('POST')
                        ->formatStateUsing(fn (?string $state): string => $state ?: 'POST')
                        ->selectablePlaceholder(false)
                        ->native(false),
                    TextInput::make('settings.webhook_secret')
                        ->label(__('packstub-form-builder::form-builder.fields.webhook_secret'))
                        ->helperText(__('packstub-form-builder::form-builder.fields.webhook_secret_hint'))
                        ->maxLength(255),
                    Toggle::make('settings.webhook_metadata')
                        ->label(__('packstub-form-builder::form-builder.fields.webhook_metadata'))
                        ->default(true)
                        ->inline(false),
                    Select::make('settings.webhook_fields')
                        ->label(__('packstub-form-builder::form-builder.fields.webhook_fields'))
                        ->helperText(__('packstub-form-builder::form-builder.fields.webhook_fields_hint'))
                        ->options(fn (Get $get): array => static::fieldOptions((array) $get('fields')))
                        ->multiple()
                        ->native(false),
                    KeyValue::make('settings.webhook_headers')
                        ->label(__('packstub-form-builder::form-builder.fields.webhook_headers'))
                        ->keyLabel('Header')
                        ->valueLabel('Value'),
                ]),
        ];
    }

    /**
     * @return array<int, Component>
     */
    protected static function designSchema(): array
    {
        return [
            Section::make(__('packstub-form-builder::form-builder.sections.display'))
                ->columns(2)
                ->schema([
                    Select::make('settings.mode')
                        ->label(__('packstub-form-builder::form-builder.fields.mode'))
                        ->helperText(__('packstub-form-builder::form-builder.fields.mode_hint'))
                        ->options([
                            'single' => __('packstub-form-builder::form-builder.fields.mode_single'),
                            'wizard' => __('packstub-form-builder::form-builder.fields.mode_wizard'),
                        ])
                        ->default('single')
                        ->formatStateUsing(fn (?string $state): string => $state ?: 'single')
                        ->selectablePlaceholder(false)
                        ->native(false)
                        ->live(),
                    Select::make('settings.layout')
                        ->label(__('packstub-form-builder::form-builder.fields.layout'))
                        ->options([
                            'stacked' => __('packstub-form-builder::form-builder.fields.layout_stacked'),
                            'horizontal' => __('packstub-form-builder::form-builder.fields.layout_horizontal'),
                        ])
                        ->default('stacked')
                        ->formatStateUsing(fn (?string $state): string => $state ?: 'stacked')
                        ->selectablePlaceholder(false)
                        ->native(false),
                ]),
            Section::make(__('packstub-form-builder::form-builder.sections.steps'))
                ->columns(2)
                ->visible(fn (Get $get): bool => $get('settings.mode') === 'wizard')
                ->schema([
                    Toggle::make('settings.wizard_progress')
                        ->label(__('packstub-form-builder::form-builder.fields.wizard_progress'))
                        ->default(true),
                    Toggle::make('settings.wizard_step_numbers')
                        ->label(__('packstub-form-builder::form-builder.fields.wizard_step_numbers'))
                        ->default(true),
                    Toggle::make('settings.wizard_back')
                        ->label(__('packstub-form-builder::form-builder.fields.wizard_back'))
                        ->default(true),
                    TextInput::make('settings.next_label')
                        ->label(__('packstub-form-builder::form-builder.fields.next_label'))
                        ->placeholder(__('packstub-form-builder::form-builder.frontend.next'))
                        ->maxLength(50),
                    TextInput::make('settings.previous_label')
                        ->label(__('packstub-form-builder::form-builder.fields.previous_label'))
                        ->placeholder(__('packstub-form-builder::form-builder.frontend.previous'))
                        ->maxLength(50),
                ]),
            Section::make(__('packstub-form-builder::form-builder.sections.page'))
                ->columns(2)
                ->collapsed()
                ->schema([
                    TextInput::make('settings.page_title')
                        ->label(__('packstub-form-builder::form-builder.fields.page_title'))
                        ->maxLength(255),
                    TextInput::make('settings.page_description')
                        ->label(__('packstub-form-builder::form-builder.fields.page_description'))
                        ->maxLength(300),
                    TextInput::make('settings.page_image')
                        ->label(__('packstub-form-builder::form-builder.fields.page_image'))
                        ->url()
                        ->maxLength(2048),
                    TextInput::make('settings.page_logo')
                        ->label(__('packstub-form-builder::form-builder.fields.page_logo'))
                        ->url()
                        ->maxLength(2048),
                ]),
            Section::make(__('packstub-form-builder::form-builder.sections.styling'))
                ->columns(2)
                ->collapsed()
                ->schema([
                    ColorPicker::make('settings.brand_color')
                        ->label(__('packstub-form-builder::form-builder.fields.brand_color'))
                        ->columnSpanFull(),
                    Textarea::make('settings.custom_css')
                        ->label(__('packstub-form-builder::form-builder.fields.custom_css'))
                        ->helperText(__('packstub-form-builder::form-builder.fields.custom_css_hint'))
                        ->rows(8)
                        ->maxLength(5000)
                        ->extraInputAttributes(['spellcheck' => 'false', 'style' => 'font-family: ui-monospace, monospace']),
                    Textarea::make('settings.custom_js')
                        ->label(__('packstub-form-builder::form-builder.fields.custom_js'))
                        ->helperText(__('packstub-form-builder::form-builder.fields.custom_js_hint'))
                        ->rows(8)
                        ->maxLength(2000)
                        ->extraInputAttributes(['spellcheck' => 'false', 'style' => 'font-family: ui-monospace, monospace']),
                ]),
        ];
    }

    /**
     * @return array<int, Component>
     */
    protected static function embedSchema(): array
    {
        $snippet = fn (string $name, string $label, \Closure $state, ?string $hint = null): TextEntry => TextEntry::make($name)
            ->label($label)
            ->helperText($hint)
            ->state($state)
            ->copyable()
            ->copyableState(fn (string $state): string => $state)
            ->copyMessage(__('packstub-form-builder::form-builder.embed.copied'))
            ->icon('heroicon-o-clipboard')
            ->iconPosition(IconPosition::After)
            ->tooltip(__('packstub-form-builder::form-builder.embed.copy'))
            ->fontFamily(FontFamily::Mono)
            ->formatStateUsing(fn (string $state): string => nl2br(e($state)))
            ->html()
            ->columnSpanFull();

        return [
            Section::make(__('packstub-form-builder::form-builder.embed.heading'))
                ->schema([
                    $snippet('embed_blade', __('packstub-form-builder::form-builder.embed.blade'), fn (Form $record): string => '<x-form-builder::form form="'.$record->slug.'" />', __('packstub-form-builder::form-builder.embed.blade_hint')),
                    $snippet('embed_livewire', __('packstub-form-builder::form-builder.embed.livewire'), fn (Form $record): string => '<livewire:form-builder form="'.$record->slug.'" />', __('packstub-form-builder::form-builder.embed.livewire_hint')),
                    $snippet('embed_page', __('packstub-form-builder::form-builder.embed.page'), fn (Form $record): string => $record->pageUrl() ?? '—')
                        ->hidden(fn (Form $record): bool => $record->pageUrl() === null),
                    $snippet('embed_iframe', __('packstub-form-builder::form-builder.embed.iframe'), fn (Form $record): string => static::iframeSnippet($record), __('packstub-form-builder::form-builder.embed.iframe_hint'))
                        ->hidden(fn (Form $record): bool => $record->pageUrl() === null),
                    $snippet('embed_script', __('packstub-form-builder::form-builder.embed.script'), fn (Form $record): string => '<div data-form-builder="'.$record->slug.'"></div>'.PHP_EOL.'<script src="'.$record->embedScriptUrl().'" async></script>', __('packstub-form-builder::form-builder.embed.script_hint'))
                        ->hidden(fn (Form $record): bool => $record->embedScriptUrl() === null),
                    $snippet('embed_json', __('packstub-form-builder::form-builder.embed.json'), fn (Form $record): string => 'GET '.$record->definitionUrl().PHP_EOL.'POST '.$record->submitUrl(), __('packstub-form-builder::form-builder.embed.json_hint')),
                ]),
        ];
    }

    public static function iframeSnippet(Form $form): string
    {
        $url = ($form->isPrivate() ? $form->shareUrl() : $form->pageUrl()).(str_contains((string) $form->pageUrl(), '?') ? '&' : '?').'embed=1';

        return '<iframe src="'.$url.'" title="'.e($form->name).'" style="width:100%;border:0" data-form-builder-frame="'.$form->slug.'"></iframe>'.PHP_EOL
            .'<script>window.addEventListener("message",function(e){if(e.data&&e.data.type==="form-builder:resize"&&e.data.slug==="'.$form->slug.'"){var f=document.querySelector(\'[data-form-builder-frame="'.$form->slug.'"]\');if(f)f.style.height=e.data.height+"px";}});</script>';
    }

    /**
     * key => label of the input fields in a builder state, optionally of some types only.
     *
     * @param  array<int, mixed>  $items
     * @param  array<int, string>|null  $types
     * @return array<string, string>
     */
    public static function fieldOptions(array $items, ?array $types = null): array
    {
        $options = [];

        foreach (Form::fromArray(['fields' => $items])->allInputFields() as $field) {
            if ($types === null || in_array($field->type::id(), $types, true)) {
                $options[$field->key] = $field->label.' ('.$field->key.')';
            }
        }

        return $options;
    }

    /**
     * @return array<int|string, string>
     */
    protected static function userOptions(): array
    {
        $model = config('auth.providers.users.model');

        if (! is_string($model) || ! class_exists($model)) {
            return [];
        }

        return $model::query()->limit(500)->get()->mapWithKeys(function (Model $user): array {
            $label = $user->getAttribute('name') ?? $user->getAttribute('email') ?? (string) $user->getKey();

            return [$user->getKey() => (string) $label];
        })->all();
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('packstub-form-builder::form-builder.fields.name'))
                    ->description(fn (Form $record): string => $record->slug)
                    ->searchable(['name', 'slug'])
                    ->sortable(),
                TextColumn::make('submissions_count')
                    ->label(__('packstub-form-builder::form-builder.fields.submissions_count'))
                    ->counts('submissions')
                    ->sortable()
                    ->alignEnd(),
                TextColumn::make('unread_submissions_count')
                    ->label(__('packstub-form-builder::form-builder.fields.unread_count'))
                    ->counts(['submissions as unread_submissions_count' => fn (Builder $query) => $query->whereNull('read_at')])
                    ->badge()
                    ->color('warning')
                    ->formatStateUsing(fn (int|string|null $state): ?string => (int) $state > 0 ? (string) $state : null)
                    ->alignEnd(),
                IconColumn::make('is_active')
                    ->label(__('packstub-form-builder::form-builder.fields.is_active'))
                    ->boolean(),
                TextColumn::make('owner.name')
                    ->label(__('packstub-form-builder::form-builder.fields.owner'))
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->label(__('packstub-form-builder::form-builder.fields.updated_at'))
                    ->since()
                    ->sortable(),
            ])
            ->defaultSort('updated_at', 'desc')
            ->filters([
                TernaryFilter::make('is_active')->label(__('packstub-form-builder::form-builder.fields.is_active')),
                Filter::make('mine')
                    ->label(__('packstub-form-builder::form-builder.fields.mine'))
                    ->toggle()
                    ->query(fn (Builder $query): Builder => $query->where($query->qualifyColumn('user_id'), auth()->id()))
                    ->visible(fn (): bool => FormBuilder::restrictedToOwner() === null),
            ])
            ->recordActions([
                EditAction::make(),
                FormActions::share(),
                Action::make('open')
                    ->label(__('packstub-form-builder::form-builder.actions.open_page'))
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn (Form $record): ?string => $record->isPrivate() ? $record->shareUrl() : $record->pageUrl(), shouldOpenInNewTab: true)
                    ->visible(fn (Form $record): bool => $record->pageUrl() !== null),
                ActionGroup::make([
                    FormActions::duplicate(),
                    FormActions::export(),
                    DeleteAction::make(),
                ]),
            ])
            ->toolbarActions([
                DeleteBulkAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            SubmissionsRelationManager::class,
            ShareLinksRelationManager::class,
            WebhookDeliveriesRelationManager::class,
        ];
    }

    public static function getWidgets(): array
    {
        return [
            SubmissionsChart::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListForms::route('/'),
            'create' => Pages\CreateForm::route('/create'),
            'edit' => Pages\EditForm::route('/{record}/edit'),
        ];
    }
}
