<?php

namespace Packstub\FormBuilder\Contracts;

use Packstub\FormBuilder\Models\FormSubmission;

/**
 * Receives every accepted submission (after the notification emails). The
 * submission is persisted unless the form has "store submissions" off, in
 * which case it is an unsaved model with the form relation loaded.
 */
interface SubmissionSink
{
    public function handle(FormSubmission $submission): void;
}
