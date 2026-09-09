<?php

namespace Packstub\FormBuilder\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Packstub\FormBuilder\FormBuilderPlugin;
use Packstub\FormBuilder\Models\FormSubmission;

class SubmissionNotification extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(public FormSubmission $submission) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('packstub-form-builder::form-builder.mail.subject', ['form' => $this->submission->form->name]),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'packstub-form-builder::mail.submission',
            with: [
                'form' => $this->submission->form,
                'rows' => $this->submission->formatted(),
                'url' => $this->panelUrl(),
            ],
        );
    }

    protected function panelUrl(): ?string
    {
        if (! $this->submission->exists) {
            return null;
        }

        try {
            return FormBuilderPlugin::submissionsUrl($this->submission->form);
        } catch (\Throwable) {
            return null;
        }
    }
}
