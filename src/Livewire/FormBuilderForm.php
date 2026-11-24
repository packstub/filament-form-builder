<?php

namespace Packstub\FormBuilder\Livewire;

use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Schemas\Components\Component as SchemaComponent;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section as SchemaSection;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Wizard;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Schemas\Schema;
use Illuminate\Contracts\View\View;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Packstub\FormBuilder\Exceptions\FormClosedException;
use Packstub\FormBuilder\Facades\FormBuilder;
use Packstub\FormBuilder\Fields\Conditions;
use Packstub\FormBuilder\Fields\Field;
use Packstub\FormBuilder\Fields\Section;
use Packstub\FormBuilder\Models\Form as FormModel;
use Packstub\FormBuilder\Submissions\Captcha;
use Packstub\FormBuilder\Submissions\PasswordGate;
use Packstub\FormBuilder\Submissions\ProtectionToken;
use Packstub\FormBuilder\Submissions\SubmissionContext;
use Packstub\FormBuilder\Submissions\Submitter;

/**
 * <livewire:form-builder form="contact" />
 *
 * The Livewire renderer: the form's fields as Filament components, in
 * sections or wizard steps, with the conditions applied live, validated in
 * place, submitted through the same pipeline as the plain renderer.
 * Filament's frontend assets reach the page through LivewireAssets.
 */
class FormBuilderForm extends Component implements HasForms
{
    use InteractsWithForms;

    #[Locked]
    public ?int $formId = null;

    /** @var array<string, mixed>|null A portable definition, when the form is not a database row. */
    #[Locked]
    public ?array $definition = null;

    #[Locked]
    public string $token = '';

    /** Preview mode: validate and show the success message, store nothing. */
    #[Locked]
    public bool $preview = false;

    #[Locked]
    public ?string $passwordKey = null;

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public bool $submitted = false;

    public ?string $message = null;

    public ?string $error = null;

    public bool $locked = false;

    public ?string $password = null;

    public ?string $passwordError = null;

    /**
     * @param  FormModel|array<string, mixed>|string|int  $form
     * @param  array<string, mixed>  $values  Values to prefill, keyed by field key.
     */
    public function mount(FormModel|array|string|int $form, array $values = [], bool $preview = false): void
    {
        $this->preview = $preview;

        if (is_array($form)) {
            $this->definition = $form;
            $model = FormModel::fromArray($form);
        } else {
            $model = FormBuilder::find($form) ?? abort(404);
            $this->formId = (int) $model->getKey();
        }

        $this->token = app(ProtectionToken::class)->make($model);
        $this->error = $model->closedReason();
        $this->locked = $model->password() !== null && ! app(PasswordGate::class)->isUnlocked($model, request());
        $this->passwordKey = $model->password() !== null && ! $this->locked ? app(PasswordGate::class)->key($model) : null;

        $prefill = $values;

        if ($model->prefillsFromQuery()) {
            foreach ($model->inputFields() as $field) {
                if (! array_key_exists($field->key, $prefill) && request()->query->has($field->key)) {
                    $prefill[$field->key] = request()->query($field->key);
                }
            }
        }

        $this->form->fill($prefill);
    }

    public function getFormModel(): FormModel
    {
        if ($this->formId !== null) {
            return FormBuilder::formModel()::query()->findOrFail($this->formId);
        }

        return FormModel::fromArray($this->definition ?? []);
    }

    public function form(Schema $schema): Schema
    {
        $model = $this->getFormModel();
        app()->instance('packstub-form-builder.current-form', $model);

        $sections = $model->sections();

        if ($model->isWizard()) {
            $components = [
                Wizard::make($sections->map(fn (Section $section): Step => $this->step($model, $section))->all())
                    ->submitAction(new HtmlString(view('packstub-form-builder::livewire.submit-button', ['label' => $model->submitLabel()])->render()))
                    ->nextAction(fn ($action) => $action->label($model->nextLabel()))
                    ->previousAction(fn ($action) => $action->label($model->previousLabel())->hidden(! $model->allowsStepNavigation()))
                    ->skippable(false),
            ];
        } else {
            $components = $sections->map(fn (Section $section): SchemaComponent => $this->section($model, $section))->all();
        }

        return $schema
            ->components($components)
            ->statePath('data');
    }

    protected function step(FormModel $model, Section $section): Step
    {
        $step = Step::make($section->label ?? '')
            ->schema([$this->grid($model, $section)])
            ->visible($this->visibleUsing($model, $section->visibility));

        if ($section->description !== null) {
            $step->description($section->description);
        }

        return $step;
    }

    protected function section(FormModel $model, Section $section): SchemaComponent
    {
        $grid = $this->grid($model, $section);

        if (! $section->isNamed()) {
            return $grid->visible($this->visibleUsing($model, $section->visibility));
        }

        return SchemaSection::make($section->label)
            ->description($section->description)
            ->schema([$grid])
            ->visible($this->visibleUsing($model, $section->visibility));
    }

    protected function grid(FormModel $model, Section $section): Grid
    {
        return Grid::make(12)->schema(
            $section->fields->map(function (Field $field) use ($model): SchemaComponent {
                $component = $field->type->formComponent($field);

                if (! $field->visibility()->isAlways()) {
                    $component->visible($this->visibleUsing($model, $field->visibility(), $field));
                }

                if ($field->required && ! $field->requirement()->isAlways() && method_exists($component, 'required')) {
                    $component->required(fn (Get $get): bool => $field->requirement()->passes($this->values($model, $get)));
                }

                if ($field->isInput() && $this->isWatched($model, $field->key) && method_exists($component, 'live')) {
                    $component->live();
                }

                if (method_exists($component, 'columnSpan')) {
                    $component->columnSpan($field->columns());
                }

                return $component;
            })->all(),
        );
    }

    /**
     * Whether other fields' conditions look at this key.
     */
    protected function isWatched(FormModel $model, string $key): bool
    {
        foreach ($model->inputFields() as $field) {
            if (in_array($key, $field->visibility()->fieldKeys(), true) || in_array($key, $field->requirement()->fieldKeys(), true)) {
                return true;
            }
        }

        foreach ($model->sections() as $section) {
            if (in_array($key, $section->visibility->fieldKeys(), true)) {
                return true;
            }
        }

        return false;
    }

    protected function visibleUsing(FormModel $model, Conditions $conditions, ?Field $field = null): \Closure|bool
    {
        if ($conditions->isAlways()) {
            return true;
        }

        return function (Get $get) use ($model, $conditions, $field): bool {
            $values = $this->values($model, $get);

            return $field === null
                ? $conditions->passes($values)
                : in_array($field->key, $model->visibleKeys($values), true);
        };
    }

    /**
     * Every input field's current value, as the conditions compare them.
     *
     * @return array<string, mixed>
     */
    protected function values(FormModel $model, Get $get): array
    {
        $values = [];

        foreach ($model->inputFields() as $field) {
            $values[$field->key] = $field->type->isComparable() ? $field->type->comparableValue($get($field->key, isAbsolute: true) ?? data_get($this->data, $field->key), $field) : null;
        }

        return $values;
    }

    public function unlock(): void
    {
        $model = $this->getFormModel();
        $gate = app(PasswordGate::class);

        if (! $gate->matches($model, $this->password)) {
            $this->passwordError = __('packstub-form-builder::form-builder.frontend.password_wrong');

            return;
        }

        $this->passwordKey = $gate->unlock($model, request());
        $this->locked = false;
        $this->password = null;
        $this->passwordError = null;
    }

    public function submit(?string $captcha = null): void
    {
        $model = $this->getFormModel();
        app()->instance('packstub-form-builder.current-form', $model);
        $state = $this->form->getState();

        $context = SubmissionContext::fromRequest(request(), 'livewire');
        $input = [
            ...$state,
            app(ProtectionToken::class)->field() => $this->token,
        ];

        if ($this->passwordKey !== null) {
            $input[PasswordGate::FIELD] = $this->passwordKey;
        }

        if ($captcha !== null && $model->captcha() !== null) {
            $input[Captcha::responseField($model->captcha())] = $captcha;
        }

        try {
            if ($this->preview) {
                app(Submitter::class)->validate($model, $input, store: false);
                $this->submitted = true;
                $this->message = $model->successMessage();

                return;
            }

            $result = app(Submitter::class)->submit($model, $input, $context);
        } catch (FormClosedException $e) {
            $this->error = $e->getMessage();

            return;
        } catch (ValidationException $e) {
            foreach ($e->errors() as $key => $messages) {
                $this->addError($key === 'captcha' ? 'captcha' : 'data.'.$key, $messages[0]);
            }

            return;
        }

        if ($result->redirectUrl() !== null) {
            $this->redirect($result->redirectUrl());

            return;
        }

        $this->submitted = true;
        $this->message = $result->message();
        $this->form->fill();
        $this->dispatch('form-builder:submitted', id: $result->submission?->getKey(), form: $model->slug);
    }

    public function render(): View
    {
        LivewireAssets::$rendered = true;
        $model = $this->getFormModel();

        return view('packstub-form-builder::livewire.form', [
            'model' => $model,
            'captcha' => Captcha::widget($model),
        ]);
    }
}
