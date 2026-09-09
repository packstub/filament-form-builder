<?php

namespace Packstub\FormBuilder\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Packstub\FormBuilder\Models\Form;
use Packstub\FormBuilder\Models\FormSubmission;
use Packstub\FormBuilder\Submissions\SubmissionContext;

/**
 * An accepted submission. The submission is saved unless the form keeps
 * "store submissions" off; either way $submission->form is loaded.
 */
class SubmissionReceived
{
    use Dispatchable;

    public function __construct(
        public readonly Form $form,
        public readonly FormSubmission $submission,
        public readonly SubmissionContext $context,
    ) {}
}
