<fieldset class="fb-fieldset" aria-describedby="{{ $field->hint ? $inputId.'-hint ' : '' }}{{ $inputId }}-error" @if ($error) aria-invalid="true" @endif>
    <legend class="fb-label">
        {{ $field->label }}
        @if ($field->required)
            <span class="fb-required" aria-hidden="true">*</span>
        @endif
    </legend>
    <div class="fb-choices">
        @foreach ($field->choices() as $choiceValue => $choiceLabel)
            <label class="fb-choice" for="{{ $inputId }}-{{ $loop->index }}">
                <input type="radio" id="{{ $inputId }}-{{ $loop->index }}" name="{{ $field->key }}" value="{{ $choiceValue }}" @checked((string) $value === (string) $choiceValue) @if ($field->required) required @endif>
                <span>{{ $choiceLabel }}</span>
            </label>
        @endforeach
    </div>
    @include('packstub-form-builder::fields._hint')
</fieldset>
