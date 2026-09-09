@php
    /** @var \Packstub\FormBuilder\Models\Form $form */
    /** @var \Packstub\FormBuilder\Http\FormState $state */
    $closed = $form->closedReason();
@endphp

@if ($styles)
    @once('packstub-form-builder-styles')
        <style {!! $nonce() ? 'nonce="'.$nonce().'"' : '' !!}>{!! $stylesheet() !!}</style>
    @endonce
@endif

<div id="{{ $formId }}" {{ $attributes->class(['fb-form', $class]) }} data-fb-form="{{ $form->slug }}">
    @if ($state->success)
        <div class="fb-success" role="status" data-fb-success>{{ $state->message ?? $form->successMessage() }}</div>
    @elseif ($closed !== null)
        <div class="fb-closed" role="status">{{ $closed }}</div>
    @else
        <form method="post" action="{{ $action }}" class="fb-form__form" novalidate data-fb-enhance="{{ $enhance ? 'true' : 'false' }}">
            @if ($csrf)
                @csrf
            @endif
            <input type="hidden" name="_fb_return" value="{{ $return }}">
            <input type="hidden" name="{{ $tokenField }}" value="{{ $token }}">
            @if ($honeypotField)
                <div class="fb-hp" aria-hidden="true">
                    <label for="{{ $formId }}-hp">{{ __('packstub-form-builder::form-builder.frontend.honeypot_label') }}</label>
                    <input type="text" id="{{ $formId }}-hp" name="{{ $honeypotField }}" tabindex="-1" autocomplete="off" value="">
                </div>
            @endif

            <div class="fb-alert fb-alert--error" role="alert" data-fb-alert{{ $state->hasErrors() ? '' : ' hidden' }}>
                {{ $state->error('form') ?? __('packstub-form-builder::form-builder.frontend.invalid') }}
            </div>

            <div class="fb-fields">
                @foreach ($form->fieldList() as $field)
                    @php
                        $inputId = $field->htmlId($formId);
                        $error = $state->error($field->key);
                        $value = $state->old($field->key, $field->default);
                    @endphp
                    <div @class(['fb-field', 'fb-field--half' => $field->width === 'half', 'fb-field--error' => $error !== null, 'fb-field--'.$field->type::id()]) data-fb-field="{{ $field->key }}">
                        @include($field->type->view(), ['field' => $field, 'inputId' => $inputId, 'error' => $error, 'value' => $value])
                        @if ($field->isInput())
                            <p class="fb-error" data-fb-error-for="{{ $field->key }}" id="{{ $inputId }}-error" @if ($error === null) hidden @endif>{{ $error }}</p>
                        @endif
                    </div>
                @endforeach
            </div>

            <div class="fb-actions">
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
