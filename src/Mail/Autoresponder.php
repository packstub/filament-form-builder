<?php

namespace Packstub\FormBuilder\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Packstub\FormBuilder\Models\FormSubmission;
use Packstub\FormBuilder\Notifications\MergeTags;

/**
 * The confirmation a visitor gets after submitting, when the form has one
 * (Notifications › Confirmation to the visitor). Subject and body take
 * merge tags; the body is Markdown.
 */
class Autoresponder extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(public FormSubmission $submission) {}

    public function envelope(): Envelope
    {
        $form = $this->submission->form;
        $subject = MergeTags::render($form->setting('autoresponder_subject'), $this->submission)
            ?: __('packstub-form-builder::form-builder.mail.autoresponder_subject');

        $from = $form->setting('notify_from_email', config('packstub-form-builder.notifications.from_email'));
        $fromName = $form->setting('notify_from_name', config('packstub-form-builder.notifications.from_name'));
        $replyTo = $form->setting('notify_reply_to');

        return new Envelope(
            from: is_string($from) && $from !== '' ? new Address($from, is_string($fromName) && $fromName !== '' ? $fromName : null) : null,
            replyTo: is_string($replyTo) && strtolower(trim($replyTo)) !== 'respondent' ? SubmissionNotification::addresses([$replyTo]) : [],
            subject: $subject,
        );
    }

    public function content(): Content
    {
        $form = $this->submission->form;
        $body = MergeTags::render($form->setting('autoresponder_body'), $this->submission)
            ?: __('packstub-form-builder::form-builder.mail.autoresponder_body');

        Branding::apply($this, $form);

        return new Content(
            markdown: 'packstub-form-builder::mail.autoresponder',
            with: [
                'form' => $form,
                'body' => $body,
                'rows' => (bool) $form->setting('autoresponder_include_values', false) ? $this->submission->formatted() : [],
                ...Branding::viewData($form),
            ],
        );
    }
}
