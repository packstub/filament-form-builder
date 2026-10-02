<?php

namespace Packstub\FormBuilder\Filament\Resources\FormResource\RelationManagers;

use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\DatePicker;
use Filament\Infolists\Components\Entry;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontFamily;
use Filament\Tables\Columns\Column;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\BaseFilter;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;
use Packstub\FormBuilder\Fields\Field;
use Packstub\FormBuilder\Filament\SubmissionsExport;
use Packstub\FormBuilder\Models\Form;
use Packstub\FormBuilder\Models\FormSubmission;
use Packstub\FormBuilder\Uploads\Uploads;

class SubmissionsRelationManager extends RelationManager
{
    protected static string $relationship = 'submissions';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('packstub-form-builder::form-builder.submissions.plural');
    }

    public static function getBadge(Model $ownerRecord, string $pageClass): ?string
    {
        $unread = $ownerRecord->submissions()->whereNull('read_at')->count();

        return $unread > 0 ? (string) $unread : null;
    }

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        /** @var Form $form */
        $form = $this->getOwnerRecord();
        $hasLinks = $form->shareLinks()->exists();

        return $table
            ->modelLabel(__('packstub-form-builder::form-builder.submissions.label'))
            ->pluralModelLabel(__('packstub-form-builder::form-builder.submissions.plural'))
            ->columns([
                IconColumn::make('read_at')
                    ->label('')
                    ->state(fn (FormSubmission $record): string => $record->isRead() ? 'read' : 'unread')
                    ->icon(fn (FormSubmission $record): string => $record->isRead() ? 'heroicon-o-envelope-open' : 'heroicon-s-envelope')
                    ->color(fn (FormSubmission $record): string => $record->isRead() ? 'gray' : 'primary')
                    ->tooltip(fn (FormSubmission $record): string => $record->isRead()
                        ? __('packstub-form-builder::form-builder.fields.read')
                        : __('packstub-form-builder::form-builder.fields.unread')),
                TextColumn::make('number')
                    ->label(__('packstub-form-builder::form-builder.fields.number'))
                    ->formatStateUsing(fn (FormSubmission $record): string => $record->reference())
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('created_at')
                    ->label(__('packstub-form-builder::form-builder.fields.created_at'))
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('summary')
                    ->label(__('packstub-form-builder::form-builder.fields.summary'))
                    ->state(fn (FormSubmission $record): string => $record->summary())
                    ->wrap()
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->where('data', 'like', "%{$search}%"))
                    ->toggleable(),
                ...$this->fieldColumns($form),
                TextColumn::make('source_url')
                    ->label(__('packstub-form-builder::form-builder.fields.source_url'))
                    ->formatStateUsing(fn (?string $state): string => $state === null ? '' : (string) (parse_url($state, PHP_URL_PATH) ?: '/'))
                    ->tooltip(fn (?string $state): ?string => $state)
                    ->limit(40)
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('channel')
                    ->label(__('packstub-form-builder::form-builder.fields.channel'))
                    ->badge()
                    ->color('gray')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('shareLink.label')
                    ->label(__('packstub-form-builder::form-builder.share.private_link'))
                    ->state(fn (FormSubmission $record): ?string => $record->shareLink === null ? null : ($record->shareLink->label ?? $record->shareLink->token))
                    ->placeholder('—')
                    ->toggleable()
                    ->visible($hasLinks),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                TernaryFilter::make('read_at')
                    ->label(__('packstub-form-builder::form-builder.submissions.filter_unread'))
                    ->nullable()
                    ->placeholder(__('filament-tables::table.filters.multi_select.placeholder'))
                    ->trueLabel(__('packstub-form-builder::form-builder.fields.read'))
                    ->falseLabel(__('packstub-form-builder::form-builder.fields.unread'))
                    ->queries(
                        true: fn (Builder $query) => $query->whereNotNull('read_at'),
                        false: fn (Builder $query) => $query->whereNull('read_at'),
                    ),
                ...$this->fieldFilters($form),
                SelectFilter::make('share_link_id')
                    ->label(__('packstub-form-builder::form-builder.share.private_link'))
                    ->options(fn (): array => $form->shareLinks()->latest()->get()->mapWithKeys(fn ($link): array => [$link->getKey() => $link->label ?? $link->token])->all())
                    ->visible($hasLinks),
            ])
            ->recordAction('view')
            ->recordActions([
                Action::make('view')
                    ->label(__('packstub-form-builder::form-builder.submissions.view'))
                    ->icon('heroicon-o-eye')
                    ->modalHeading(fn (FormSubmission $record): string => __('packstub-form-builder::form-builder.submissions.label').' '.$record->reference())
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel(__('filament::components/modal.actions.close.label'))
                    ->slideOver()
                    ->mountUsing(fn (FormSubmission $record) => $record->isRead() || $record->markRead())
                    ->schema(fn (FormSubmission $record): array => $this->detailsSchema($record)),
                Action::make('edit')
                    ->label(__('packstub-form-builder::form-builder.submissions.edit'))
                    ->icon('heroicon-o-pencil-square')
                    ->modalHeading(fn (FormSubmission $record): string => __('packstub-form-builder::form-builder.submissions.edit').' '.$record->reference())
                    ->slideOver()
                    ->fillForm(fn (FormSubmission $record): array => $record->data ?? [])
                    ->schema(fn (): array => $this->editSchema($form))
                    ->action(function (FormSubmission $record, array $data) use ($form): void {
                        app()->instance('packstub-form-builder.current-form', $form);
                        $values = $record->data ?? [];

                        foreach ($form->inputFields() as $field) {
                            if (array_key_exists($field->key, $data)) {
                                $values[$field->key] = $field->type->normalize($data[$field->key], $field);
                            }
                        }

                        $record->update(['data' => $values]);

                        Notification::make()->title(__('packstub-form-builder::form-builder.submissions.saved'))->success()->send();
                    }),
                Action::make('toggleRead')
                    ->label(fn (FormSubmission $record): string => $record->isRead()
                        ? __('packstub-form-builder::form-builder.submissions.mark_unread')
                        : __('packstub-form-builder::form-builder.submissions.mark_read'))
                    ->icon(fn (FormSubmission $record): string => $record->isRead() ? 'heroicon-o-envelope' : 'heroicon-o-envelope-open')
                    ->action(fn (FormSubmission $record) => $record->markRead(! $record->isRead())),
                DeleteAction::make(),
            ])
            ->headerActions([
                Action::make('export')
                    ->label(__('packstub-form-builder::form-builder.submissions.export'))
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('gray')
                    ->action(fn () => SubmissionsExport::download($form, $this->getFilteredTableQuery())),
                Action::make('exportXlsx')
                    ->label(__('packstub-form-builder::form-builder.submissions.export_xlsx'))
                    ->icon('heroicon-o-table-cells')
                    ->color('gray')
                    ->visible(fn (): bool => SubmissionsExport::hasXlsx())
                    ->action(fn () => SubmissionsExport::download($form, $this->getFilteredTableQuery(), 'xlsx')),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('markRead')
                        ->label(__('packstub-form-builder::form-builder.submissions.mark_read'))
                        ->icon('heroicon-o-envelope-open')
                        ->action(fn (Collection $records) => $records->each->markRead())
                        ->deselectRecordsAfterCompletion(),
                    BulkAction::make('exportSelected')
                        ->label(__('packstub-form-builder::form-builder.submissions.export'))
                        ->icon('heroicon-o-arrow-down-tray')
                        ->action(fn (Collection $records) => SubmissionsExport::download($form, $records)),
                    DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading(__('packstub-form-builder::form-builder.submissions.empty'))
            ->emptyStateDescription(__('packstub-form-builder::form-builder.submissions.empty_description'));
    }

    /**
     * One column per field of the form (the first two shown by default),
     * searchable, sortable and filterable by the field's type.
     *
     * @return array<int, Column>
     */
    protected function fieldColumns(Form $form): array
    {
        $columns = [];
        $index = 0;

        foreach ($form->inputFields() as $field) {
            if ($field->type::id() === 'hidden') {
                continue;
            }

            $column = $field->type->tableColumn($field) ?? TextColumn::make('data.'.$field->key)
                ->state(fn (FormSubmission $record): string => $field->type->format($record->value($field->key), $field))
                ->limit(40)
                ->wrap();

            $column
                ->label($field->label)
                ->searchable(query: fn (Builder $query, string $search): Builder => $query->where('data->'.$field->key, 'like', "%{$search}%"))
                ->toggleable(isToggledHiddenByDefault: $index >= 2);

            if (! $column->isSortable()) {
                $column->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderBy('data->'.$field->key, $direction));
            }

            $columns[] = $column;
            $index++;
        }

        return $columns;
    }

    /**
     * @return array<int, BaseFilter>
     */
    protected function fieldFilters(Form $form): array
    {
        $filters = [];

        foreach ($form->inputFields() as $field) {
            $filter = $field->type->tableFilter($field) ?? $this->defaultFilter($field);

            if ($filter !== null) {
                $filters[] = $filter;
            }
        }

        return $filters;
    }

    protected function defaultFilter(Field $field): ?BaseFilter
    {
        $path = 'data->'.$field->key;

        if ($field->type->hasChoices()) {
            return SelectFilter::make('field_'.$field->key)
                ->label($field->label)
                ->options($field->choices())
                ->multiple()
                ->query(function (Builder $query, array $data) use ($path, $field): Builder {
                    $values = array_values(array_filter((array) ($data['values'] ?? []), fn ($value): bool => $value !== null && $value !== ''));

                    if ($values === []) {
                        return $query;
                    }

                    return $query->where(function (Builder $query) use ($path, $field, $values): void {
                        foreach ($values as $value) {
                            $field->type->acceptsMultiple()
                                ? $query->orWhereJsonContains($path, $value)
                                : $query->orWhere($path, $value);
                        }
                    });
                });
        }

        if (in_array($field->type::id(), ['checkbox', 'toggle', 'consent'], true)) {
            return TernaryFilter::make('field_'.$field->key)
                ->label($field->label)
                ->queries(
                    true: fn (Builder $query) => $query->where($path, true),
                    false: fn (Builder $query) => $query->where(fn (Builder $query) => $query->where($path, false)->orWhereNull($path)),
                );
        }

        if (in_array($field->type::id(), ['date', 'datetime'], true)) {
            return Filter::make('field_'.$field->key)
                ->label($field->label)
                ->schema([
                    DatePicker::make('from')->label(__('packstub-form-builder::form-builder.rules.after_or_equal'))->native(),
                    DatePicker::make('until')->label(__('packstub-form-builder::form-builder.rules.before_or_equal'))->native(),
                ])
                ->query(fn (Builder $query, array $data): Builder => $query
                    ->when($data['from'] ?? null, fn (Builder $query, $from) => $query->where($path, '>=', $from))
                    ->when($data['until'] ?? null, fn (Builder $query, $until) => $query->where($path, '<=', $until.' 23:59:59')));
        }

        return null;
    }

    /**
     * @return array<int, Component>
     */
    protected function editSchema(Form $form): array
    {
        app()->instance('packstub-form-builder.current-form', $form);

        return [
            Grid::make(12)->schema(
                $form->inputFields()
                    ->filter(fn (Field $field): bool => $field->type::id() !== 'file')
                    ->map(fn (Field $field) => $field->type->formComponent($field)->columnSpan($field->columns()))
                    ->values()
                    ->all(),
            ),
        ];
    }

    /**
     * @return array<int, Component>
     */
    protected function detailsSchema(FormSubmission $record): array
    {
        $rows = collect($record->formatted());

        return [
            Section::make(__('packstub-form-builder::form-builder.submissions.data'))
                ->schema($rows->map(function (array $row, string $key) use ($record): Entry {
                    $field = $record->fieldFor($key);

                    if (($custom = $field->type->detailEntry($field)) !== null) {
                        return $custom->label($row['label']);
                    }

                    $entry = TextEntry::make('data.'.$key)->label($row['label']);

                    if ($field->type::id() === 'file') {
                        $links = [];

                        foreach (Uploads::files($record->value($key)) as $index => $path) {
                            $url = Uploads::url($record, $key, $index);
                            $links[] = $url === null
                                ? e(Uploads::originalName($path))
                                : '<a href="'.e($url).'" class="fi-link" target="_blank" rel="noopener">'.e(Uploads::originalName($path)).'</a>';
                        }

                        return $entry->html()->state($links === [] ? '—' : new HtmlString(implode('<br>', $links)));
                    }

                    $display = $field->type->display($record->value($key), $field);

                    if ($display instanceof Htmlable) {
                        return $entry->html()->state(new HtmlString($display->toHtml()));
                    }

                    return $entry
                        ->state($display === '' ? '—' : $display)
                        ->copyable($display !== '');
                })->values()->all()),
            Section::make(__('packstub-form-builder::form-builder.submissions.details'))
                ->collapsed()
                ->columns(2)
                ->schema([
                    TextEntry::make('number')->label(__('packstub-form-builder::form-builder.fields.number'))->state(fn (FormSubmission $record): string => $record->reference()),
                    TextEntry::make('created_at')->label(__('packstub-form-builder::form-builder.fields.created_at'))->dateTime(),
                    TextEntry::make('channel')->label(__('packstub-form-builder::form-builder.fields.channel'))->badge()->color('gray'),
                    TextEntry::make('user_id')->label(__('packstub-form-builder::form-builder.fields.user'))->placeholder('—'),
                    TextEntry::make('share_link')->label(__('packstub-form-builder::form-builder.share.private_link'))->state(fn (FormSubmission $record): ?string => $record->shareLink?->label ?? $record->shareLink?->token)->placeholder('—')->hidden(fn (FormSubmission $record): bool => $record->share_link_id === null),
                    TextEntry::make('source_url')->label(__('packstub-form-builder::form-builder.fields.source_url'))->placeholder('—')->columnSpanFull(),
                    TextEntry::make('ip')->label(__('packstub-form-builder::form-builder.fields.ip'))->placeholder('—')->fontFamily(FontFamily::Mono),
                    TextEntry::make('user_agent')->label(__('packstub-form-builder::form-builder.fields.user_agent'))->placeholder('—'),
                    KeyValueEntry::make('meta')->hidden(fn (FormSubmission $record): bool => blank($record->meta))->columnSpanFull(),
                ]),
        ];
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }
}
