<?php

use Filament\Support\Facades\FilamentAsset;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use Packstub\FormBuilder\Livewire\LivewireAssets;

beforeEach(function (): void {
    LivewireAssets::reset();
    contactForm();

    Route::middleware('web')->get('/page', fn () => Blade::render(<<<'BLADE'
        <!DOCTYPE html>
        <html><head><title>Page</title></head>
        <body><main><livewire:form-builder form="contact" /></main></body></html>
        BLADE));

    Route::middleware('web')->get('/page-with-assets', fn () => Blade::render(<<<'BLADE'
        <!DOCTYPE html>
        <html><head>@filamentStyles<link href="/css/my-theme.css" rel="stylesheet"></head>
        <body><livewire:form-builder form="contact" />@filamentScripts</body></html>
        BLADE));

    Route::middleware('web')->get('/plain', fn () => Blade::render(<<<'BLADE'
        <!DOCTYPE html>
        <html><head><title>Plain</title></head><body><p>No form here.</p></body></html>
        BLADE));
});

it('puts Filament\'s assets on a page that renders the Livewire component', function (): void {
    $html = $this->get('/page')->assertOk()->getContent();

    $head = substr($html, 0, strpos($html, '</head>'));
    $body = substr($html, strpos($html, '<body>'));

    expect($head)->toContain('--primary-500:')
        ->toContain('href="http://localhost/css/packstub/filament-form-builder/form-builder-livewire.css?v=')
        ->and($body)->toContain('window.filamentData')
        ->toContain('/js/filament/support/support.js')
        ->and(substr_count($html, 'window.filamentData'))->toBe(1);

    // Filament's scripts come after the component and before </body>.
    expect(strpos($body, 'window.filamentData'))->toBeGreaterThan(strpos($body, 'fb-form--livewire'))
        ->toBeLessThan(strpos($body, '</body>'));
});

it('leaves a page alone when it prints @filamentStyles and @filamentScripts itself', function (): void {
    $html = $this->get('/page-with-assets')->assertOk()->getContent();

    expect(substr_count($html, 'window.filamentData'))->toBe(1)
        ->and(substr_count($html, '--primary-500:'))->toBe(1)
        ->and($html)->not->toContain('form-builder-livewire.css')
        ->toContain('/css/my-theme.css');
});

it('injects nothing on a page without the component', function (): void {
    $html = $this->get('/plain')->assertOk()->getContent();

    expect($html)->not->toContain('window.filamentData')
        ->not->toContain('--primary-500:')
        ->not->toContain('form-builder-livewire.css');
});

it('injects nothing when turned off', function (): void {
    config()->set('packstub-form-builder.frontend.livewire_assets', false);

    $html = $this->get('/page')->assertOk()->getContent();

    expect($html)->toContain('fb-form--livewire')
        ->not->toContain('window.filamentData')
        ->not->toContain('form-builder-livewire.css');
});

it('links the configured stylesheet instead of the compiled one', function (): void {
    config()->set('packstub-form-builder.frontend.livewire_theme', 'css/filament/filament/app.css');

    $html = $this->get('/page')->assertOk()->getContent();

    expect($html)->toContain('href="http://localhost/css/filament/filament/app.css" rel="stylesheet"')
        ->not->toContain('form-builder-livewire.css');

    config()->set('packstub-form-builder.frontend.livewire_theme', 'https://cdn.example.com/theme.css');

    expect($this->get('/page')->getContent())->toContain('href="https://cdn.example.com/theme.css"');

    config()->set('packstub-form-builder.frontend.livewire_theme', false);

    $html = $this->get('/page')->getContent();

    expect($html)->toContain('--primary-500:')
        ->not->toContain('rel="stylesheet" data-navigate-track');
});

it('registers the stylesheet as a Filament asset that panels do not print', function (): void {
    expect(LivewireAssets::stylesheetUrl())->toStartWith('http://localhost/css/packstub/filament-form-builder/form-builder-livewire.css?v=');

    $panelStyles = FilamentAsset::renderStyles();

    expect($panelStyles)->not->toContain('form-builder-livewire.css');
});
