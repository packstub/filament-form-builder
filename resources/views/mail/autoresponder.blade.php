<x-mail::layout>
{{-- Header: the form's logo (Design tab) or the app name --}}
<x-slot:header>
<x-mail::header :url="config('app.url')">
@if (! empty($logo) && \Packstub\FormBuilder\Mail\Branding::rendersHtml())
<img src="{{ $logo }}" alt="{{ config('app.name') }}" style="border: 0; height: auto; max-height: 48px; max-width: 240px; width: auto;">
@else
{{ config('app.name') }}
@endif
</x-mail::header>
</x-slot:header>

{!! $body !!}

@if ($rows !== [])
<x-mail::table>
| {{ __('packstub-form-builder::form-builder.mail.field') }} | {{ __('packstub-form-builder::form-builder.mail.value') }} |
|:--|:--|
@foreach ($rows as $row)
| {{ $row['label'] }} | {{ str_replace(['|', "\n"], ['\|', ' '], $row['value']) }} |
@endforeach
</x-mail::table>
@endif

{{-- Footer --}}
<x-slot:footer>
<x-mail::footer>
© {{ date('Y') }} {{ config('app.name') }}. {{ __('All rights reserved.') }}
</x-mail::footer>
</x-slot:footer>
</x-mail::layout>
