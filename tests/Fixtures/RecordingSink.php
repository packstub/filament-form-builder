<?php

namespace Packstub\FormBuilder\Tests\Fixtures;

use Packstub\FormBuilder\Contracts\SubmissionSink;
use Packstub\FormBuilder\Models\FormSubmission;

class RecordingSink implements SubmissionSink
{
    /** @var array<int, FormSubmission> */
    public static array $received = [];

    public function handle(FormSubmission $submission): void
    {
        static::$received[] = $submission;
    }
}
