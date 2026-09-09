@include('packstub-form-builder::fields._label')
<select
    class="fb-input fb-select"
    id="{{ $inputId }}"
    name="{{ $field->key }}"
    @if ($field->required) required aria-required="true" @endif
    aria-describedby="{{ $field->hint ? $inputId.'-hint ' : '' }}{{ $inputId }}-error"
    @if ($error) aria-invalid="true" @endif
>
    <option value="">{{ $field->placeholder ?? __('packstub-form-builder::form-builder.frontend.select_placeholder') }}</option>
    @foreach ($field->choices() as $choiceValue => $choiceLabel)
        <option value="{{ $choiceValue }}" @selected((string) $value === (string) $choiceValue)>{{ $choiceLabel }}</option>
    @endforeach
</select>
@include('packstub-form-builder::fields._hint')
