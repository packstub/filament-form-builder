<fieldset class="fb-fieldset" aria-describedby="{{ $field->hint ? $inputId.'-hint ' : '' }}{{ $inputId }}-error" @if ($error) aria-invalid="true" @endif>
    <legend class="fb-label">
        {{ $field->label }}
        @if ($field->required)
            <span class="fb-required" aria-hidden="true">*</span>
        @endif
    </legend>
    @if (($columns = $field->type->choiceColumns($field)) > 1)
        <div class="fb-choices fb-choices--columns" style="--fb-choice-columns: {{ $columns }}">
    @else
        <div class="fb-choices">
    @endif
        @foreach ($field->choices() as $choiceValue => $choiceLabel)
            <label class="fb-choice" for="{{ $inputId }}-{{ $loop->index }}">
                <input type="radio" id="{{ $inputId }}-{{ $loop->index }}" name="{{ $field->key }}" value="{{ $choiceValue }}" @checked((string) $value === (string) $choiceValue) @if ($field->required && ! $field->isConditional()) required @endif>
                <span>{{ $choiceLabel }}</span>
            </label>
        @endforeach
    </div>
    @include('packstub-form-builder::fields._hint')
</fieldset>
