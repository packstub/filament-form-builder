<?php

namespace Packstub\FormBuilder\View\Components;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\Str;
use Illuminate\View\Component;
use Packstub\FormBuilder\Facades\FormBuilder;
use Packstub\FormBuilder\Http\FormState;
use Packstub\FormBuilder\Models\Form as FormModel;
use Packstub\FormBuilder\Submissions\ProtectionToken;
use Packstub\FormBuilder\Submissions\SpamGuard;

/**
 * <x-form-builder::form form="contact" />
 *
 * The plain renderer: an HTML form posting to the submit endpoint. Works
 * without JavaScript and without a session; with the enhancement script it
 * submits in place.
 */
class Form extends Component
{
    public ?FormModel $form;

    public FormState $state;

    public string $token = '';

    public string $tokenField;

    public ?string $honeypotField;

    public string $formId;

    public bool $csrf;

    public function __construct(
        FormModel|string|int $form,
        public ?string $action = null,
        public ?string $return = null,
        public ?bool $enhance = null,
        public ?bool $styles = null,
        public ?string $id = null,
        public ?string $class = null,
    ) {
        $this->form = FormBuilder::find($form);
        $this->enhance ??= (bool) config('packstub-form-builder.frontend.enhance', true);
        $this->styles ??= (bool) config('packstub-form-builder.frontend.styles', true);
        $this->tokenField = app(ProtectionToken::class)->field();
        $this->csrf = request()->hasSession();

        if ($this->form === null) {
            $this->state = new FormState;
            $this->honeypotField = null;
            $this->formId = 'form-'.(is_scalar($form) ? Str::slug((string) $form) : 'missing');

            return;
        }

        $this->state = FormState::for($this->form);
        $this->token = app(ProtectionToken::class)->make($this->form);
        $this->honeypotField = $this->form->usesHoneypot() ? app(SpamGuard::class)->honeypotField() : null;
        $this->formId = $this->id ?? 'form-'.$this->form->slug;
        $this->action ??= $this->form->submitUrl();
        $this->return ??= request()->fullUrl();
    }

    public function render(): View
    {
        return view('packstub-form-builder::components.form');
    }

    public function shouldRender(): bool
    {
        return $this->form !== null;
    }

    public function stylesheet(): string
    {
        return (string) file_get_contents(__DIR__.'/../../../resources/css/form-builder.css');
    }

    public function script(): string
    {
        return (string) file_get_contents(__DIR__.'/../../../resources/js/form-builder.js');
    }

    public function nonce(): ?string
    {
        return Vite::cspNonce();
    }
}
