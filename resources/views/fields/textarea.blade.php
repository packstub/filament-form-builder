@include('packstub-form-builder::fields._label')
<textarea
    class="fb-input fb-textarea"
    id="{{ $inputId }}"
    name="{{ $field->key }}"
    rows="{{ (int) ($field->option('rows') ?: 4) }}"
    @if ($field->placeholder) placeholder="{{ $field->placeholder }}" @endif
    @if ($field->required) required aria-required="true" @endif
    @if (filled($field->option('max_length'))) maxlength="{{ $field->option('max_length') }}" @endif
    aria-describedby="{{ $field->hint ? $inputId.'-hint ' : '' }}{{ $inputId }}-error"
    @if ($error) aria-invalid="true" @endif
>{{ is_scalar($value) ? $value : '' }}</textarea>
@include('packstub-form-builder::fields._hint')
