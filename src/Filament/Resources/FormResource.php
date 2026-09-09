<?php

namespace Packstub\FormBuilder\Filament\Resources;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
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
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Packstub\FormBuilder\Filament\FieldBlocks;
use Packstub\FormBuilder\Filament\Resources\FormResource\Pages;
use Packstub\FormBuilder\Filament\Resources\FormResource\RelationManagers\SubmissionsRelationManager;
use Packstub\FormBuilder\FormBuilder;
use Packstub\FormBuilder\FormBuilderPlugin;
use Packstub\FormBuilder\Models\Form;

class FormResource extends Resource
{
    protected static ?string $slug = 'forms';

    protected static ?string $recordTitleAttribute = 'name';

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

        $unread = FormBuilder::submissionModel()::query()->unread()->count();

        return $unread > 0 ? (string) $unread : null;
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
            Section::make(__('packstub-form-builder::form-builder.sections.notifications'))
                ->columns(2)
                ->schema([
                    TagsInput::make('notification_emails')
                        ->label(__('packstub-form-builder::form-builder.fields.notification_emails'))
                        ->helperText(__('packstub-form-builder::form-builder.fields.notification_emails_hint'))
                        ->placeholder('name@example.com')
                        ->nestedRecursiveRules(['email']),
                    Toggle::make('store_submissions')
                        ->label(__('packstub-form-builder::form-builder.fields.store_submissions'))
                        ->helperText(__('packstub-form-builder::form-builder.fields.store_submissions_hint'))
                        ->default(true)
                        ->inline(false),
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
                    Toggle::make('settings.require_login')
                        ->label(__('packstub-form-builder::form-builder.fields.require_login'))
                        ->default(false),
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
            ->copyMessage(__('packstub-form-builder::form-builder.embed.copied'))
            ->fontFamily(FontFamily::Mono)
            ->columnSpanFull();

        return [
            Section::make(__('packstub-form-builder::form-builder.embed.heading'))
                ->schema([
                    $snippet('embed_blade', __('packstub-form-builder::form-builder.embed.blade'), fn (Form $record): string => '<x-form-builder::form form="'.$record->slug.'" />', __('packstub-form-builder::form-builder.embed.blade_hint')),
                    $snippet('embed_livewire', __('packstub-form-builder::form-builder.embed.livewire'), fn (Form $record): string => '<livewire:form-builder form="'.$record->slug.'" />', __('packstub-form-builder::form-builder.embed.livewire_hint')),
                    $snippet('embed_page', __('packstub-form-builder::form-builder.embed.page'), fn (Form $record): string => $record->pageUrl() ?? '—')
                        ->hidden(fn (Form $record): bool => $record->pageUrl() === null),
                    $snippet('embed_json', __('packstub-form-builder::form-builder.embed.json'), fn (Form $record): string => 'GET '.$record->definitionUrl().PHP_EOL.'POST '.$record->submitUrl(), __('packstub-form-builder::form-builder.embed.json_hint')),
                ]),
        ];
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
                TextColumn::make('updated_at')
                    ->label(__('packstub-form-builder::form-builder.fields.updated_at'))
                    ->since()
                    ->sortable(),
            ])
            ->defaultSort('updated_at', 'desc')
            ->filters([
                TernaryFilter::make('is_active')->label(__('packstub-form-builder::form-builder.fields.is_active')),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('open')
                    ->label(__('packstub-form-builder::form-builder.actions.open_page'))
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn (Form $record): ?string => $record->pageUrl(), shouldOpenInNewTab: true)
                    ->visible(fn (Form $record): bool => $record->pageUrl() !== null),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                DeleteBulkAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            SubmissionsRelationManager::class,
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
