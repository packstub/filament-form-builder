<?php

namespace Packstub\FormBuilder\Listeners;

use Illuminate\Support\Facades\Mail;
use Packstub\FormBuilder\Events\SubmissionReceived;
use Packstub\FormBuilder\Mail\SubmissionNotification;

class SendSubmissionNotifications
{
    public function handle(SubmissionReceived $event): void
    {
        $emails = $event->form->notificationEmails();

        if ($emails === []) {
            return;
        }

        $mailable = new SubmissionNotification($event->submission);
        $pending = Mail::to($emails);

        config('packstub-form-builder.submissions.queue_notifications', true) && $event->submission->exists
            ? $pending->queue($mailable)
            : $pending->send($mailable);
    }
}
