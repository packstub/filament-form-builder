<?php

namespace Packstub\FormBuilder\Listeners;

use Packstub\FormBuilder\Events\SubmissionReceived;
use Packstub\FormBuilder\FormBuilder;

class DispatchToSinks
{
    public function __construct(protected FormBuilder $formBuilder) {}

    public function handle(SubmissionReceived $event): void
    {
        foreach ($this->formBuilder->sinks() as $sink) {
            $sink->handle($event->submission);
        }
    }
}
