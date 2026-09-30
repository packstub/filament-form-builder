<?php

namespace Packstub\FormBuilder\View\Components;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\Str;
use Illuminate\View\Component;
use Packstub\FormBuilder\Facades\FormBuilder;
use Packstub\FormBuilder\Fields\Field;
use Packstub\FormBuilder\Http\FormState;
use Packstub\FormBuilder\Models\Form as FormModel;
use Packstub\FormBuilder\Submissions\Captcha;
use Packstub\FormBuilder\Submissions\PasswordGate;
use Packstub\FormBuilder\Submissions\ProtectionToken;
use Packstub\FormBuilder\Submissions\SpamGuard;

/**
 * <x-form-builder::form form="contact" />
 *
 * The plain renderer: an HTML form posting to the submit endpoint. Works
 * without JavaScript and without a session; with the enhancement script it
 * submits in place, applies the conditions and walks the steps.
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

    public bool $locked = false;

    public bool $wrongPassword = false;

    public ?string $passwordKey = null;

    /** @var array{script: string, html: string}|null */
    public ?array $captcha = null;

    /** @var array<string, mixed> */
    public array $prefill = [];

    /**
     * @param  FormModel|array<string, mixed>|string|int  $form  A model, a slug, an id, or a portable array (Form::fromArray).
     * @param  array<string, mixed>  $values  Values to prefill, keyed by field key.
     * @param  string|null  $link  The token of the share link the form was opened through.
     */
    public function __construct(
        FormModel|array|string|int $form,
        public ?string $action = null,
        public ?string $return = null,
        public ?bool $enhance = null,
        public ?bool $styles = null,
        public ?string $id = null,
        public ?string $class = null,
        public array $values = [],
        public ?string $link = null,
    ) {
        $this->form = is_array($form) ? FormModel::fromArray($form) : FormBuilder::find($form);
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

        $request = request();
        $this->state = FormState::for($this->form);
        $this->token = app(ProtectionToken::class)->make($this->form);
        $this->honeypotField = $this->form->usesHoneypot() ? app(SpamGuard::class)->honeypotField() : null;
        $this->formId = $this->id ?? 'form-'.$this->form->slug;
        $this->action ??= $this->form->submitUrl();
        $this->return ??= $request->fullUrl();
        $this->captcha = Captcha::widget($this->form);
        $this->prefill = $this->prefillValues($request);

        if ($this->form->password() !== null) {
            $gate = app(PasswordGate::class);
            $this->locked = ! $gate->isUnlocked($this->form, $request);
            $this->wrongPassword = (string) $request->query('fb_locked') === $this->form->slug;
            $this->passwordKey = $this->locked ? null : $gate->key($this->form);
        }
    }

    /**
     * The values fields start with: the :values attribute, then the page
     * URL's query parameters (when the form allows it), by field key.
     *
     * @return array<string, mixed>
     */
    protected function prefillValues(Request $request): array
    {
        $values = $this->values;

        if ($this->form?->prefillsFromQuery()) {
            foreach ($this->form->inputFields() as $field) {
                if (! array_key_exists($field->key, $values) && $request->query->has($field->key)) {
                    $values[$field->key] = $request->query($field->key);
                }
            }
        }

        return $values;
    }

    /**
     * The value a field starts with: the old input after a failed POST,
     * else the prefill, else the field's default.
     */
    public function valueOf(Field $field): mixed
    {
        if ($this->state->hasErrors()) {
            return $this->state->old($field->key, $this->prefill[$field->key] ?? $field->default);
        }

        return $this->prefill[$field->key] ?? $field->default;
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

    /**
     * The definition the script reads: the conditions and the steps.
     */
    public function logic(): string
    {
        $form = $this->form;

        return json_encode([
            'wizard' => $form->isWizard() ? [
                'progress' => $form->showsProgress(),
                'numbers' => $form->showsStepNumbers(),
                'navigation' => $form->allowsStepNavigation(),
            ] : null,
            'validate' => $form->validateUrl(),
            'fields' => $form->inputFields()->map(fn (Field $field): array => [
                'visibility' => $field->visibility()->isAlways() ? null : $field->visibility()->toArray(),
                'requirement' => $field->required && ! $field->requirement()->isAlways() ? $field->requirement()->toArray() : null,
                'required' => $field->required,
                'type' => $field->type::id(),
            ])->all(),
            'sections' => $form->sections()->map(fn ($section): array => [
                'key' => $section->key,
                'visibility' => $section->visibility->isAlways() ? null : $section->visibility->toArray(),
                'fields' => $section->fields->filter(fn (Field $field): bool => $field->isInput())->map(fn (Field $field): string => $field->key)->values()->all(),
            ])->values()->all(),
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    }

    /**
     * The strings the script and the embed need.
     *
     * @return array<string, string>
     */
    public static function strings(): array
    {
        return [
            'invalid' => __('packstub-form-builder::form-builder.frontend.invalid'),
            'failed' => __('packstub-form-builder::form-builder.frontend.failed'),
            'step_of' => __('packstub-form-builder::form-builder.frontend.step_of', ['current' => ':current', 'total' => ':total']),
            'select_placeholder' => __('packstub-form-builder::form-builder.frontend.select_placeholder'),
            'honeypot_label' => __('packstub-form-builder::form-builder.frontend.honeypot_label'),
            'password_prompt' => __('packstub-form-builder::form-builder.frontend.password_prompt'),
            'password_label' => __('packstub-form-builder::form-builder.frontend.password_label'),
            'password_wrong' => __('packstub-form-builder::form-builder.frontend.password_wrong'),
            'unlock' => __('packstub-form-builder::form-builder.frontend.unlock'),
        ];
    }
}
