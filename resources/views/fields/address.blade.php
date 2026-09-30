@php
    /** @var \Packstub\FormBuilder\Fields\Types\AddressField $type */
    $type = $field->type;
    $value = is_array($value) ? $value : [];
    $requiredParts = $field->required && ! $field->isConditional() ? $type->requiredParts($field) : [];
@endphp
<fieldset class="fb-fieldset fb-address" aria-describedby="{{ $field->hint ? $inputId.'-hint ' : '' }}{{ $inputId }}-error" @if ($error) aria-invalid="true" @endif>
    <legend class="fb-label">
        {{ $field->label }}
        @if ($field->required)
            <span class="fb-required" aria-hidden="true">*</span>
        @endif
    </legend>
    <div class="fb-address__parts">
        @foreach ($type->parts($field) as $part => $partLabel)
            @php
                $partId = $inputId.'-'.str_replace('_', '-', $part);
                $partValue = is_scalar($value[$part] ?? null) ? (string) $value[$part] : '';
                $partRequired = in_array($part, $requiredParts, true);
            @endphp
            <div class="fb-address__part fb-address__part--{{ str_replace('_', '-', $part) }}">
                <label class="fb-sublabel" for="{{ $partId }}">{{ $partLabel }}</label>
                @if ($part === 'country')
                    <select class="fb-input fb-select" id="{{ $partId }}" name="{{ $field->key }}[{{ $part }}]" autocomplete="country" @if ($partRequired) required aria-required="true" @endif>
                        <option value="">{{ __('packstub-form-builder::form-builder.frontend.select_placeholder') }}</option>
                        @foreach ($type->countries($field) as $code => $country)
                            <option value="{{ $code }}" @selected($partValue === $code)>{{ $country }}</option>
                        @endforeach
                    </select>
                @else
                    <input class="fb-input" type="text" id="{{ $partId }}" name="{{ $field->key }}[{{ $part }}]" value="{{ $partValue }}" autocomplete="{{ \Packstub\FormBuilder\Fields\Types\AddressField::AUTOCOMPLETE[$part] }}" @if ($partRequired) required aria-required="true" @endif>
                @endif
            </div>
        @endforeach
    </div>
    @include('packstub-form-builder::fields._hint')
</fieldset>
