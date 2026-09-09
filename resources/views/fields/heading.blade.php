@php $level = in_array($field->option('level'), ['h2', 'h3', 'h4'], true) ? $field->option('level') : 'h3'; @endphp
<{{ $level }} class="fb-heading" id="{{ $inputId }}">{{ $field->label }}</{{ $level }}>
