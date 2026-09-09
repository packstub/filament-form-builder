<label class="fb-label" for="{{ $inputId }}">
    {{ $field->label }}
    @if ($field->required)
        <span class="fb-required" aria-hidden="true">*</span>
    @endif
</label>
