<?php

namespace Packstub\FormBuilder\Filament\Resources\FormResource\RelationManagers;

use Filament\Actions\Action;
use Filament\Actions\DeleteBulkAction;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontFamily;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Packstub\FormBuilder\Models\Form;
use Packstub\FormBuilder\Models\WebhookDelivery;
use Packstub\FormBuilder\Webhooks\DeliverWebhook;

/**
 * The log of a form's webhook deliveries and chat channel messages, shown
 * once the form has a webhook URL or a channel: status, attempts,
 * response, and a retry.
 */
class WebhookDeliveriesRelationManager extends RelationManager
{
    protected static string $relationship = 'webhookDeliveries';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('packstub-form-builder::form-builder.webhooks.deliveries');
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        /** @var Form $ownerRecord */
        return filled($ownerRecord->setting('webhook_url')) || filled($ownerRecord->setting('channels')) || $ownerRecord->webhookDeliveries()->exists();
    }

    public static function getBadge(Model $ownerRecord, string $pageClass): ?string
    {
        $failed = $ownerRecord->webhookDeliveries()->where('status', WebhookDelivery::FAILED)->count();

        return $failed > 0 ? (string) $failed : null;
    }

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('status')
                    ->label(__('packstub-form-builder::form-builder.webhooks.status'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => __('packstub-form-builder::form-builder.webhooks.'.$state))
                    ->color(fn (string $state): string => match ($state) {
                        WebhookDelivery::DELIVERED => 'success',
                        WebhookDelivery::FAILED => 'danger',
                        default => 'warning',
                    }),
                TextColumn::make('created_at')
                    ->label(__('packstub-form-builder::form-builder.fields.created_at'))
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('event')
                    ->label(__('packstub-form-builder::form-builder.webhooks.event'))
                    ->badge()
                    ->color('gray')
                    ->formatStateUsing(fn (WebhookDelivery $record): string => $record->isChannelMessage()
                        ? __('packstub-form-builder::form-builder.channels.'.substr($record->event, 8))
                        : __('packstub-form-builder::form-builder.webhooks.webhook')),
                TextColumn::make('submission.number')
                    ->label(__('packstub-form-builder::form-builder.submissions.label'))
                    ->formatStateUsing(fn ($state, WebhookDelivery $record): string => $record->submission?->reference() ?? '—'),
                TextColumn::make('url')
                    ->label('URL')
                    ->limit(40)
                    ->tooltip(fn (WebhookDelivery $record): string => $record->url),
                TextColumn::make('attempts')
                    ->label(__('packstub-form-builder::form-builder.webhooks.attempts'))
                    ->alignEnd(),
                TextColumn::make('response_status')
                    ->label(__('packstub-form-builder::form-builder.webhooks.response'))
                    ->placeholder('—')
                    ->description(fn (WebhookDelivery $record): ?string => $record->error),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->label(__('packstub-form-builder::form-builder.webhooks.status'))
                    ->options([
                        WebhookDelivery::PENDING => __('packstub-form-builder::form-builder.webhooks.pending'),
                        WebhookDelivery::DELIVERED => __('packstub-form-builder::form-builder.webhooks.delivered'),
                        WebhookDelivery::FAILED => __('packstub-form-builder::form-builder.webhooks.failed'),
                    ]),
            ])
            ->recordAction('view')
            ->recordActions([
                Action::make('view')
                    ->label(__('packstub-form-builder::form-builder.submissions.view'))
                    ->icon('heroicon-o-eye')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel(__('filament::components/modal.actions.close.label'))
                    ->slideOver()
                    ->schema([
                        TextEntry::make('url')->label('URL')->fontFamily(FontFamily::Mono)->copyable(),
                        TextEntry::make('error')->label(__('packstub-form-builder::form-builder.webhooks.failed'))->placeholder('—'),
                        TextEntry::make('response_body')->label(__('packstub-form-builder::form-builder.webhooks.response'))->placeholder('—')->fontFamily(FontFamily::Mono),
                        KeyValueEntry::make('payload.data.data')->label(__('packstub-form-builder::form-builder.submissions.data')),
                    ]),
                Action::make('retry')
                    ->label(__('packstub-form-builder::form-builder.webhooks.retry'))
                    ->icon('heroicon-o-arrow-path')
                    ->visible(fn (WebhookDelivery $record): bool => ! $record->isDelivered())
                    ->action(function (WebhookDelivery $record): void {
                        $record->forceFill(['status' => WebhookDelivery::PENDING, 'attempts' => 0, 'error' => null, 'next_attempt_at' => null])->save();
                        DeliverWebhook::start($record);
                    }),
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
