<?php

namespace Packstub\FormBuilder\Testing;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Event;
use Illuminate\Testing\Assert as PHPUnit;
use Packstub\FormBuilder\Events\SpamDetected;
use Packstub\FormBuilder\Events\SubmissionReceived;
use Packstub\FormBuilder\FormBuilder;
use Packstub\FormBuilder\Models\Form;
use Packstub\FormBuilder\Models\FormSubmission;

/**
 * FormBuilder::fake(): submissions still validate and store, but the
 * emails, panel notifications, webhooks, channel messages and sinks do not
 * run; what was submitted (and dropped as spam) is recorded for the
 * assertions below.
 */
class FormBuilderFake extends FormBuilder
{
    /** @var array<int, SubmissionReceived> */
    protected array $received = [];

    /** @var array<int, SpamDetected> */
    protected array $spam = [];

    public function listen(): static
    {
        Event::listen(SubmissionReceived::class, fn (SubmissionReceived $event) => $this->received[] = $event);
        Event::listen(SpamDetected::class, fn (SpamDetected $event) => $this->spam[] = $event);

        return $this;
    }

    /**
     * The submissions recorded for a form (or every form), optionally
     * filtered by a callback receiving the submission and its data.
     *
     * @param  (callable(FormSubmission, array<string, mixed>): bool)|null  $callback
     * @return Collection<int, FormSubmission>
     */
    public function submitted(Form|string|int|null $form = null, ?callable $callback = null): Collection
    {
        return collect($this->received)
            ->filter(fn (SubmissionReceived $event): bool => $form === null || $this->matches($event->form, $form))
            ->map(fn (SubmissionReceived $event): FormSubmission => $event->submission)
            ->filter(fn (FormSubmission $submission): bool => $callback === null || (bool) $callback($submission, $submission->data ?? []))
            ->values();
    }

    /**
     * @param  (callable(FormSubmission, array<string, mixed>): bool)|null  $callback
     */
    public function assertSubmitted(Form|string|int $form, ?callable $callback = null): static
    {
        PHPUnit::assertTrue(
            $this->submitted($form, $callback)->isNotEmpty(),
            'The expected ['.$this->name($form).'] submission was not received.',
        );

        return $this;
    }

    /**
     * @param  (callable(FormSubmission, array<string, mixed>): bool)|null  $callback
     */
    public function assertNotSubmitted(Form|string|int $form, ?callable $callback = null): static
    {
        PHPUnit::assertTrue(
            $this->submitted($form, $callback)->isEmpty(),
            'An unexpected ['.$this->name($form).'] submission was received.',
        );

        return $this;
    }

    public function assertSubmittedCount(Form|string|int $form, int $count): static
    {
        $actual = $this->submitted($form)->count();

        PHPUnit::assertSame($count, $actual, "The [{$this->name($form)}] form received {$actual} submissions instead of {$count}.");

        return $this;
    }

    public function assertNothingSubmitted(): static
    {
        PHPUnit::assertEmpty($this->received, count($this->received).' unexpected submissions were received.');

        return $this;
    }

    public function assertSpamDetected(Form|string|int $form, ?string $reason = null): static
    {
        $found = collect($this->spam)->contains(fn (SpamDetected $event): bool => $this->matches($event->form, $form) && ($reason === null || $event->reason === $reason));

        PHPUnit::assertTrue($found, 'No spam was detected on ['.$this->name($form).']'.($reason === null ? '' : " for [{$reason}]").'.');

        return $this;
    }

    protected function matches(Form $actual, Form|string|int $expected): bool
    {
        return match (true) {
            $expected instanceof Form => $actual->is($expected) || ($actual->slug === $expected->slug && ! $expected->exists),
            is_int($expected) || ctype_digit((string) $expected) => (string) $actual->getKey() === (string) $expected,
            default => $actual->slug === $expected,
        };
    }

    protected function name(Form|string|int $form): string
    {
        return $form instanceof Form ? $form->slug : (string) $form;
    }
}
