<?php

namespace Packstub\FormBuilder\Listeners;

use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;
use Packstub\FormBuilder\Events\SubmissionReceived;
use Packstub\FormBuilder\FormBuilder;
use Packstub\FormBuilder\FormBuilderPlugin;

/**
 * A Filament database notification to the users picked in the form's
 * Notifications settings ("Notify in the panel"). Needs the notifications
 * table Filament's database notifications use.
 */
class SendPanelNotifications
{
    public function handle(SubmissionReceived $event): void
    {
        if (FormBuilder::faking()) {
            return;
        }

        $ids = array_values(array_filter((array) $event->form->setting('notify_users', [])));

        if ($ids === [] || ! $event->submission->exists) {
            return;
        }

        $model = config('auth.providers.users.model');

        if (! is_string($model) || ! class_exists($model)) {
            return;
        }

        $users = $model::query()->whereKey($ids)->get();

        // A picked user who is no member of the form's tenant (any more) is
        // not told about its submissions; nobody is, when the tenant can't
        // be loaded to check.
        $column = FormBuilder::tenantColumn();

        if ($column !== null && $event->form->getAttribute($column) !== null) {
            $tenant = FormBuilder::tenantOf($event->form);

            $users = $tenant === null
                ? $users->take(0)
                : $users->filter(fn (Model $user): bool => FormBuilder::canAccessTenant($user, $tenant))->values();
        }

        if ($users->isEmpty()) {
            return;
        }

        $url = null;

        try {
            $url = FormBuilderPlugin::submissionsUrl($event->form);
        } catch (\Throwable) {
            //
        }

        $notification = Notification::make()
            ->title(__('packstub-form-builder::form-builder.notifications.title', ['form' => $event->form->name]))
            ->body($event->submission->summary())
            ->icon('heroicon-o-inbox-arrow-down');

        if ($url !== null) {
            $notification->actions([
                Action::make('view')
                    ->label(__('packstub-form-builder::form-builder.notifications.view'))
                    ->url($url)
                    ->markAsRead(),
            ]);
        }

        $notification->sendToDatabase($users, isEventDispatched: true);
    }
}
