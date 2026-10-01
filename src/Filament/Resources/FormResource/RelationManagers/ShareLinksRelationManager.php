<?php

namespace Packstub\FormBuilder\Filament\Resources\FormResource\RelationManagers;

use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontFamily;
use Filament\Support\Enums\IconPosition;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Packstub\FormBuilder\Filament\FormActions;
use Packstub\FormBuilder\Models\Form;
use Packstub\FormBuilder\Models\ShareLink;

/**
 * The share links of a form (shown once it is private or has links): each
 * with its label, link, expiry, submissions and status; revoke one without
 * touching the others.
 */
class ShareLinksRelationManager extends RelationManager
{
    protected static string $relationship = 'shareLinks';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('packstub-form-builder::form-builder.share.links');
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        /** @var Form $ownerRecord */
        return app('router')->has('packstub-form-builder.share')
            && ($ownerRecord->isPrivate() || $ownerRecord->shareLinks()->exists());
    }

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->modelLabel(__('packstub-form-builder::form-builder.share.private_link'))
            ->pluralModelLabel(__('packstub-form-builder::form-builder.share.links'))
            ->columns([
                TextColumn::make('label')
                    ->label(__('packstub-form-builder::form-builder.share.label'))
                    ->placeholder('—')
                    ->searchable(),
                TextColumn::make('token')
                    ->label(__('packstub-form-builder::form-builder.share.url'))
                    ->state(fn (ShareLink $record): string => (string) $record->url())
                    ->copyable()
                    ->copyMessage(__('packstub-form-builder::form-builder.embed.copied'))
                    ->icon('heroicon-o-clipboard')
                    ->iconPosition(IconPosition::After)
                    ->tooltip(__('packstub-form-builder::form-builder.embed.copy'))
                    ->fontFamily(FontFamily::Mono),
                TextColumn::make('expires_at')
                    ->label(__('packstub-form-builder::form-builder.share.expires_at'))
                    ->dateTime()
                    ->placeholder('—')
                    ->sortable(),
                TextColumn::make('submissions_count')
                    ->label(__('packstub-form-builder::form-builder.share.submissions'))
                    ->counts('submissions')
                    ->formatStateUsing(fn (int|string|null $state, ShareLink $record): string => $record->max_submissions === null ? (string) (int) $state : (int) $state.' / '.$record->max_submissions)
                    ->alignEnd(),
                TextColumn::make('status')
                    ->label(__('packstub-form-builder::form-builder.share.status'))
                    ->state(fn (ShareLink $record): string => $record->status())
                    ->formatStateUsing(fn (string $state): string => __('packstub-form-builder::form-builder.share.'.$state))
                    ->badge()
                    ->color(fn (string $state): string => $state === ShareLink::ACTIVE ? 'success' : 'gray'),
                TextColumn::make('created_at')
                    ->label(__('packstub-form-builder::form-builder.fields.created_at'))
                    ->since()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->headerActions([
                Action::make('createLink')
                    ->label(__('packstub-form-builder::form-builder.share.new'))
                    ->icon('heroicon-o-link')
                    ->schema([
                        TextInput::make('label')
                            ->label(__('packstub-form-builder::form-builder.share.label'))
                            ->placeholder(__('packstub-form-builder::form-builder.share.label_placeholder'))
                            ->maxLength(255),
                        DateTimePicker::make('expires_at')
                            ->label(__('packstub-form-builder::form-builder.share.expires_at'))
                            ->helperText(__('packstub-form-builder::form-builder.share.expires_at_hint'))
                            ->native(),
                        TextInput::make('max_submissions')
                            ->label(__('packstub-form-builder::form-builder.share.max_submissions'))
                            ->helperText(__('packstub-form-builder::form-builder.share.max_submissions_hint'))
                            ->integer()
                            ->minValue(1),
                    ])
                    ->action(function (array $data): void {
                        /** @var Form $form */
                        $form = $this->getOwnerRecord();
                        $link = FormActions::createShareLink($form, $data);

                        Notification::make()
                            ->title(__('packstub-form-builder::form-builder.share.created'))
                            ->body($link->url())
                            ->success()
                            ->send();
                    }),
            ])
            ->recordActions([
                Action::make('revoke')
                    ->label(__('packstub-form-builder::form-builder.share.revoke'))
                    ->icon('heroicon-o-no-symbol')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (ShareLink $record): bool => $record->revoked_at === null)
                    ->action(function (ShareLink $record): void {
                        $record->revoke();

                        Notification::make()->title(__('packstub-form-builder::form-builder.share.revoked_notice'))->success()->send();
                    }),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                DeleteBulkAction::make(),
            ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }
}
