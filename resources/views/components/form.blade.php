@php
    /** @var \Packstub\FormBuilder\Models\Form $form */
    /** @var \Packstub\FormBuilder\Http\FormState $state */
    $closed = $form->closedReason();
    $wizard = $form->isWizard();
    $sections = $form->sections();
    $hasFiles = $form->inputFields()->contains(fn ($field): bool => $field->type::id() === 'file');
    $customCss = $form->customCss();
    $customJs = $form->customJs();
    $brand = $form->brandColor();
    $strings = \Packstub\FormBuilder\View\Components\Form::strings();
@endphp

@if ($styles)
    @once('packstub-form-builder-styles')
        <style {!! $nonce() ? 'nonce="'.$nonce().'"' : '' !!}>{!! $stylesheet() !!}</style>
    @endonce
@endif
@if ($customCss)
    <style {!! $nonce() ? 'nonce="'.$nonce().'"' : '' !!}>{!! preg_replace('~</style~i', '', $customCss) !!}</style>
@endif

<div id="{{ $formId }}" {{ $attributes->class(['fb-form', 'fb-form--horizontal' => $form->layout() === 'horizontal', 'fb-form--wizard' => $wizard, $class]) }} data-fb-form="{{ $form->slug }}" @if ($brand) style="--fb-color-primary: {{ $brand }}" @endif>
    @if ($state->success)
        <div class="fb-success" role="status" data-fb-success>{{ $state->message ?? $form->successMessage() }}</div>
    @elseif ($closed !== null)
        <div class="fb-closed" role="status">{{ $closed }}</div>
    @elseif ($locked)
        <form method="post" action="{{ route('packstub-form-builder.unlock', $form) }}" class="fb-form__form fb-form__unlock" data-fb-unlock>
            @if ($csrf)
                @csrf
            @endif
            <input type="hidden" name="_fb_return" value="{{ $return }}">
            <p class="fb-paragraph">{{ $strings['password_prompt'] }}</p>
            <div class="fb-alert fb-alert--error" role="alert" data-fb-alert {{ $wrongPassword ? '' : 'hidden' }}>{{ $strings['password_wrong'] }}</div>
            <div class="fb-fields">
                <div class="fb-field fb-field--password">
                    <label class="fb-label" for="{{ $formId }}-password">{{ $strings['password_label'] }}</label>
                    <input class="fb-input" type="password" id="{{ $formId }}-password" name="password" required autocomplete="off">
                </div>
            </div>
            <div class="fb-actions">
                <button type="submit" class="fb-submit">{{ $strings['unlock'] }}</button>
            </div>
        </form>
    @else
        <form method="post" action="{{ $action }}" class="fb-form__form" novalidate data-fb-enhance="{{ $enhance ? 'true' : 'false' }}" @if ($hasFiles) enctype="multipart/form-data" @endif data-fb-message-invalid="{{ $strings['invalid'] }}" data-fb-message-failed="{{ $strings['failed'] }}" data-fb-step-of="{{ $strings['step_of'] }}">
            @if ($csrf)
                @csrf
            @endif
            <input type="hidden" name="_fb_return" value="{{ $return }}">
            <input type="hidden" name="{{ $tokenField }}" value="{{ $token }}">
            @if ($passwordKey)
                <input type="hidden" name="_fb_key" value="{{ $passwordKey }}">
            @endif
            @if ($link)
                <input type="hidden" name="_fb_link" value="{{ $link }}">
            @endif
            @if ($honeypotField)
                <div class="fb-hp" aria-hidden="true">
                    <label for="{{ $formId }}-hp">{{ $strings['honeypot_label'] }}</label>
                    <input type="text" id="{{ $formId }}-hp" name="{{ $honeypotField }}" tabindex="-1" autocomplete="off" value="">
                </div>
            @endif

            <script type="application/json" data-fb-logic>{!! $logic() !!}</script>

            <div class="fb-alert fb-alert--error" role="alert" data-fb-alert{{ $state->hasErrors() ? '' : ' hidden' }}>
                {{ $state->error('form') ?? $strings['invalid'] }}
            </div>

            @if ($wizard && $form->showsProgress())
                <div class="fb-progress" data-fb-progress hidden>
                    <div class="fb-progress__bar"><span class="fb-progress__value" data-fb-progress-value style="width: 0%"></span></div>
                    <p class="fb-progress__label" data-fb-progress-label></p>
                </div>
            @endif

            @foreach ($sections as $sectionIndex => $section)
                <section class="fb-section {{ $section->isNamed() ? 'fb-section--named' : '' }}" data-fb-section="{{ $section->key }}" data-fb-step="{{ $sectionIndex }}">
                    @if ($section->isNamed())
                        <h3 class="fb-section__title">
                            @if ($wizard && $form->showsStepNumbers())
                                <span class="fb-section__number">{{ $sectionIndex + 1 }}</span>
                            @endif
                            {{ $section->label }}
                        </h3>
                        @if ($section->description)
                            <p class="fb-section__description">{{ $section->description }}</p>
                        @endif
                    @endif

                    <div class="fb-fields">
                        @foreach ($section->fields as $field)
                            @php
                                $inputId = $field->htmlId($formId);
                                $error = $state->error($field->key);
                                $value = $valueOf($field);
                            @endphp
                            <div @class(['fb-field', 'fb-field--'.$field->width, 'fb-field--error' => $error !== null, 'fb-field--'.$field->type::id(), 'fb-field--conditional' => $field->isConditional()]) data-fb-field="{{ $field->key }}" data-fb-cols="{{ $field->columns() }}">
                                @include($field->type->view(), ['field' => $field, 'inputId' => $inputId, 'error' => $error, 'value' => $value])
                                @if ($field->isInput())
                                    <p class="fb-error" data-fb-error-for="{{ $field->key }}" id="{{ $inputId }}-error" @if ($error === null) hidden @endif>{{ $error }}</p>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </section>
            @endforeach

            @if ($captcha)
                <div class="fb-field fb-field--captcha" data-fb-field="captcha">
                    {!! $captcha['html'] !!}
                    <p class="fb-error" data-fb-error-for="captcha" @if ($state->error('captcha') === null) hidden @endif>{{ $state->error('captcha') }}</p>
                </div>
            @endif

            <div class="fb-actions">
                @if ($wizard)
                    <button type="button" class="fb-button fb-button--secondary" data-fb-previous hidden>{{ $form->previousLabel() }}</button>
                    <button type="button" class="fb-submit" data-fb-next hidden>{{ $form->nextLabel() }}</button>
                @endif
                <button type="submit" class="fb-submit" data-fb-submit>{{ $form->submitLabel() }}</button>
            </div>
        </form>
    @endif
</div>

@if ($enhance)
    @once('packstub-form-builder-script')
        <script {!! $nonce() ? 'nonce="'.$nonce().'"' : '' !!}>{!! $script() !!}</script>
    @endonce
@endif
@if ($captcha && $closed === null && ! $locked && ! $state->success)
    @once('packstub-form-builder-captcha-'.$form->captcha())
        <script src="{{ $captcha['script'] }}" async defer {!! $nonce() ? 'nonce="'.$nonce().'"' : '' !!}></script>
    @endonce
@endif
@if ($customJs && $closed === null && ! $locked && ! $state->success)
    <script {!! $nonce() ? 'nonce="'.$nonce().'"' : '' !!}>(function (form) { {!! preg_replace('~</script~i', '', $customJs) !!} })(document.getElementById(@json($formId)));</script>
@endif
