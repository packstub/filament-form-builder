{!! view(\Packstub\FormBuilder\Mail\Branding::baseTheme())->render() !!}
@if (! empty($brand))
a { color: {{ $brand }}; }
.header a { color: #3d4852; }
.button-primary { background-color: {{ $brand }}; border-bottom: 8px solid {{ $brand }}; border-left: 18px solid {{ $brand }}; border-right: 18px solid {{ $brand }}; border-top: 8px solid {{ $brand }}; }
.content-cell h1 { border-left: 4px solid {{ $brand }}; padding-left: 12px; }
@endif
