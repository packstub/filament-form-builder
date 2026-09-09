@include('packstub-form-builder::fields._label')
<input
    class="fb-input"
    type="{{ $field->type->inputType() }}"
    id="{{ $inputId }}"
    name="{{ $field->key }}"
    value="{{ is_scalar($value) ? $value : '' }}"
    @if ($field->placeholder) placeholder="{{ $field->placeholder }}" @endif
    @if ($field->required) required aria-required="true" @endif
    @if ($field->type::id() === 'number')
        @if (filled($field->option('min'))) min="{{ $field->option('min') }}" @endif
        @if (filled($field->option('max'))) max="{{ $field->option('max') }}" @endif
        @if (filled($field->option('step'))) step="{{ $field->option('step') }}" @endif
    @elseif ($field->type::id() === 'date')
        @if (filled($field->option('min'))) min="{{ $field->option('min') }}" @endif
        @if (filled($field->option('max'))) max="{{ $field->option('max') }}" @endif
    @else
        @if (filled($field->option('max_length'))) maxlength="{{ $field->option('max_length') }}" @endif
    @endif
    @if ($field->type::id() === 'email') autocomplete="email" @elseif ($field->type::id() === 'phone') autocomplete="tel" @endif
    aria-describedby="{{ $field->hint ? $inputId.'-hint ' : '' }}{{ $inputId }}-error"
    @if ($error) aria-invalid="true" @endif
>
@include('packstub-form-builder::fields._hint')
