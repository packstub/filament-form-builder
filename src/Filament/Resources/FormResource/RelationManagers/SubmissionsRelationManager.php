<?php

namespace Packstub\FormBuilder\Filament\Resources\FormResource\RelationManagers;

use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontFamily;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Packstub\FormBuilder\Filament\SubmissionsCsv;
use Packstub\FormBuilder\Models\Form;
use Packstub\FormBuilder\Models\FormSubmission;

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

        return $table
            ->modelLabel(__('packstub-form-builder::form-builder.submissions.label'))
            ->pluralModelLabel(__('packstub-form-builder::form-builder.submissions.plural'))
            ->columns([
                IconColumn::make('read_at')
                    ->label('')
                    ->icon(fn (FormSubmission $record): string => $record->isRead() ? 'heroicon-o-envelope-open' : 'heroicon-s-envelope')
                    ->color(fn (FormSubmission $record): string => $record->isRead() ? 'gray' : 'primary')
                    ->tooltip(fn (FormSubmission $record): string => $record->isRead()
                        ? __('packstub-form-builder::form-builder.fields.read')
                        : __('packstub-form-builder::form-builder.fields.unread')),
                TextColumn::make('created_at')
                    ->label(__('packstub-form-builder::form-builder.fields.created_at'))
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('summary')
                    ->label(__('packstub-form-builder::form-builder.fields.summary'))
                    ->state(fn (FormSubmission $record): string => $record->summary())
                    ->wrap()
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->where('data', 'like', "%{$search}%")),
                TextColumn::make('source_url')
                    ->label(__('packstub-form-builder::form-builder.fields.source_url'))
                    ->formatStateUsing(fn (?string $state): string => $state === null ? '' : (string) (parse_url($state, PHP_URL_PATH) ?: '/'))
                    ->tooltip(fn (?string $state): ?string => $state)
                    ->limit(40)
                    ->toggleable(),
                TextColumn::make('channel')
                    ->label(__('packstub-form-builder::form-builder.fields.channel'))
                    ->badge()
                    ->color('gray')
                    ->toggleable(isToggledHiddenByDefault: true),
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
            ])
            ->recordAction('view')
            ->recordActions([
                Action::make('view')
                    ->label(__('packstub-form-builder::form-builder.submissions.view'))
                    ->icon('heroicon-o-eye')
                    ->modalHeading(fn (FormSubmission $record): string => __('packstub-form-builder::form-builder.submissions.label').' #'.$record->getKey())
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel(__('filament::components/modal.actions.close.label'))
                    ->slideOver()
                    ->mountUsing(fn (FormSubmission $record) => $record->isRead() || $record->markRead())
                    ->schema(fn (FormSubmission $record): array => $this->detailsSchema($record)),
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
                    ->action(fn () => SubmissionsCsv::download($form, $this->getFilteredTableQuery())),
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
                        ->action(fn (Collection $records) => SubmissionsCsv::download($form, $records)),
                    DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading(__('packstub-form-builder::form-builder.submissions.empty'))
            ->emptyStateDescription(__('packstub-form-builder::form-builder.submissions.empty_description'));
    }

    /**
     * @return array<int, Component>
     */
    protected function detailsSchema(FormSubmission $record): array
    {
        $rows = collect($record->formatted());

        return [
            Section::make(__('packstub-form-builder::form-builder.submissions.data'))
                ->schema($rows->map(fn (array $row, string $key): TextEntry => TextEntry::make('data.'.$key)
                    ->label($row['label'])
                    ->state($row['value'] === '' ? '—' : $row['value'])
                    ->copyable(),
                )->values()->all()),
            Section::make(__('packstub-form-builder::form-builder.submissions.details'))
                ->collapsed()
                ->columns(2)
                ->schema([
                    TextEntry::make('created_at')->label(__('packstub-form-builder::form-builder.fields.created_at'))->dateTime(),
                    TextEntry::make('channel')->label(__('packstub-form-builder::form-builder.fields.channel'))->badge()->color('gray'),
                    TextEntry::make('source_url')->label(__('packstub-form-builder::form-builder.fields.source_url'))->placeholder('—')->columnSpanFull(),
                    TextEntry::make('ip')->label(__('packstub-form-builder::form-builder.fields.ip'))->placeholder('—')->fontFamily(FontFamily::Mono),
                    TextEntry::make('user_id')->label(__('packstub-form-builder::form-builder.fields.user'))->placeholder('—'),
                    TextEntry::make('user_agent')->label(__('packstub-form-builder::form-builder.fields.user_agent'))->placeholder('—')->columnSpanFull(),
                    KeyValueEntry::make('meta')->hidden(fn (FormSubmission $record): bool => blank($record->meta))->columnSpanFull(),
                ]),
        ];
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }
}
