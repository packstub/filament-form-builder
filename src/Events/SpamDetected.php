<?php

namespace Packstub\FormBuilder\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Packstub\FormBuilder\Models\Form;
use Packstub\FormBuilder\Submissions\SubmissionContext;

/**
 * A submission silently dropped by the honeypot or the time trap.
 */
class SpamDetected
{
    use Dispatchable;

    /**
     * @param  array<string, mixed>  $input
     */
    public function __construct(
        public readonly Form $form,
        public readonly string $reason,
        public readonly array $input,
        public readonly SubmissionContext $context,
    ) {}
}
