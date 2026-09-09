<label class="fb-choice fb-choice--single" for="{{ $inputId }}">
    <input type="checkbox" id="{{ $inputId }}" name="{{ $field->key }}" value="1" @checked(filter_var($value, FILTER_VALIDATE_BOOLEAN)) @if ($field->required) required aria-required="true" @endif aria-describedby="{{ $field->hint ? $inputId.'-hint ' : '' }}{{ $inputId }}-error" @if ($error) aria-invalid="true" @endif>
    <span>
        {{ $field->label }}
        @if ($field->required)
            <span class="fb-required" aria-hidden="true">*</span>
        @endif
    </span>
</label>
@include('packstub-form-builder::fields._hint')
