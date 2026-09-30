<?php

namespace Packstub\FormBuilder\Listeners;

use Illuminate\Support\Facades\Mail;
use Packstub\FormBuilder\Events\SubmissionReceived;
use Packstub\FormBuilder\FormBuilder;
use Packstub\FormBuilder\Mail\Autoresponder;

class SendAutoresponder
{
    public function handle(SubmissionReceived $event): void
    {
        if (FormBuilder::faking()) {
            return;
        }

        $form = $event->form;

        if (! (bool) $form->setting('autoresponder', false)) {
            return;
        }

        $key = $form->setting('autoresponder_field');
        $email = null;

        if (is_string($key) && $key !== '') {
            $email = $event->submission->value($key);
        } else {
            foreach ($form->inputFields() as $field) {
                if ($field->type::id() === 'email' && filled($event->submission->value($field->key))) {
                    $email = $event->submission->value($field->key);
                    break;
                }
            }
        }

        if (! is_string($email) || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return;
        }

        $mailable = new Autoresponder($event->submission);
        $pending = Mail::to($email);

        config('packstub-form-builder.submissions.queue_notifications', true) && $event->submission->exists
            ? $pending->queue($mailable)
            : $pending->send($mailable);
    }
}
