<x-mail::message>
# {{ __('packstub-form-builder::form-builder.mail.heading', ['form' => $form->name]) }}

<x-mail::table>
| {{ __('packstub-form-builder::form-builder.mail.field') }} | {{ __('packstub-form-builder::form-builder.mail.value') }} |
|:--|:--|
@foreach ($rows as $row)
| {{ $row['label'] }} | {{ str_replace(['|', "\n"], ['\|', ' '], $row['value']) }} |
@endforeach
</x-mail::table>

@if ($url)
<x-mail::button :url="$url">
{{ __('packstub-form-builder::form-builder.mail.view') }}
</x-mail::button>
@endif
</x-mail::message>
