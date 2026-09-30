<?php

namespace Packstub\FormBuilder\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Packstub\FormBuilder\FormBuilderPlugin;
use Packstub\FormBuilder\Models\FormSubmission;
use Packstub\FormBuilder\Notifications\MergeTags;
use Packstub\FormBuilder\Uploads\Uploads;

/**
 * The email to the team for a submission. From, reply-to, CC, BCC and the
 * subject come from the form's Notifications settings, with the config
 * defaults behind them.
 */
class SubmissionNotification extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(public FormSubmission $submission) {}

    public function envelope(): Envelope
    {
        $form = $this->submission->form;
        $subject = MergeTags::render($form->setting('notify_subject'), $this->submission)
            ?: __('packstub-form-builder::form-builder.mail.subject', ['form' => $form->name]);

        $from = $form->setting('notify_from_email', config('packstub-form-builder.notifications.from_email'));
        $fromName = $form->setting('notify_from_name', config('packstub-form-builder.notifications.from_name'));

        return new Envelope(
            from: is_string($from) && $from !== '' ? new Address($from, is_string($fromName) && $fromName !== '' ? $fromName : null) : null,
            replyTo: static::addresses([$this->replyToAddress()]),
            cc: static::addresses((array) $form->setting('notify_cc', [])),
            bcc: static::addresses((array) $form->setting('notify_bcc', [])),
            subject: $subject,
        );
    }

    public function content(): Content
    {
        $form = $this->submission->form;
        Branding::apply($this, $form);

        return new Content(
            markdown: 'packstub-form-builder::mail.submission',
            with: [
                'form' => $form,
                'submission' => $this->submission,
                'rows' => $this->submission->formatted(),
                'url' => $this->panelUrl(),
                ...Branding::viewData($form),
            ],
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        if (! $this->submission->form->attachesFiles()) {
            return [];
        }

        $disk = Storage::disk(Uploads::disk());
        $max = (int) config('packstub-form-builder.uploads.attach_max_kb', 10240) * 1024;
        $attachments = [];
        $total = 0;

        foreach ($this->submission->fields ?? [] as $key => $meta) {
            if (($meta['type'] ?? null) !== 'file') {
                continue;
            }

            foreach (Uploads::files($this->submission->value($key)) as $path) {
                if (! $disk->exists($path)) {
                    continue;
                }

                $size = (int) $disk->size($path);

                if ($total + $size > $max) {
                    continue;
                }

                $total += $size;
                $attachments[] = Attachment::fromStorageDisk(Uploads::disk(), $path)->as(Uploads::originalName($path));
            }
        }

        return $attachments;
    }

    /**
     * The reply-to address: the setting, or with "respondent" the email
     * the visitor typed (the first email field with a value).
     */
    protected function replyToAddress(): ?string
    {
        $setting = $this->submission->form->setting('notify_reply_to');

        if (! is_string($setting) || trim($setting) === '') {
            return null;
        }

        if (strtolower(trim($setting)) !== 'respondent') {
            return trim($setting);
        }

        foreach ($this->submission->fields ?? [] as $key => $meta) {
            if (($meta['type'] ?? null) === 'email' && filter_var($this->submission->value($key), FILTER_VALIDATE_EMAIL)) {
                return (string) $this->submission->value($key);
            }
        }

        return null;
    }

    /**
     * @param  array<int, mixed>  $addresses
     * @return array<int, Address>
     */
    public static function addresses(array $addresses): array
    {
        return array_values(array_map(
            fn (string $address): Address => new Address($address),
            array_filter(array_map(fn ($address): string => is_string($address) ? trim($address) : '', $addresses), fn (string $address): bool => filter_var($address, FILTER_VALIDATE_EMAIL) !== false),
        ));
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
