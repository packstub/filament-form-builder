<?php

namespace Packstub\FormBuilder\Livewire;

use Filament\Support\Facades\FilamentAsset;
use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Support\HtmlString;

/**
 * Puts Filament's frontend on any page that renders <livewire:form-builder>,
 * the way Livewire injects its own script: once the response is built, the
 * colour variables, the renderer's stylesheet and Filament's scripts go into
 * <head> and before </body>, each only when the page does not print it
 * already (a panel page, or a layout with @filamentStyles / @filamentScripts).
 */
class LivewireAssets
{
    public const PACKAGE = 'packstub/filament-form-builder';

    public const STYLESHEET = 'form-builder-livewire';

    public static bool $rendered = false;

    public static function reset(): void
    {
        static::$rendered = false;
    }

    /**
     * The URL of the stylesheet the renderer links: the package's compiled
     * one, a stylesheet of your own (`frontend.livewire_theme`), or null to
     * link none.
     */
    public static function stylesheetUrl(): ?string
    {
        $theme = config('packstub-form-builder.frontend.livewire_theme');

        if ($theme === false) {
            return null;
        }

        if (is_string($theme) && $theme !== '') {
            return str_starts_with($theme, 'http://') || str_starts_with($theme, 'https://') || str_starts_with($theme, '//')
                ? $theme
                : asset(ltrim($theme, '/'));
        }

        return FilamentAsset::getStyleHref(static::STYLESHEET, static::PACKAGE);
    }

    /**
     * What goes into <head>: @filamentStyles (colour variables and fonts)
     * and the stylesheet link.
     */
    public static function styles(): HtmlString
    {
        $url = static::stylesheetUrl();

        return new HtmlString(
            FilamentAsset::renderStyles()
            .($url !== null ? "\n<link href=\"{$url}\" rel=\"stylesheet\" data-navigate-track />" : ''),
        );
    }

    /**
     * What goes before </body>: @filamentScripts.
     */
    public static function scripts(): HtmlString
    {
        return new HtmlString(FilamentAsset::renderScripts());
    }

    public static function inject(RequestHandled $event): void
    {
        $response = $event->response;
        $rendered = static::$rendered;

        static::reset();

        if (! $rendered) {
            return;
        }

        if (! config('packstub-form-builder.frontend.livewire_assets', true)) {
            return;
        }

        if (! str_contains((string) $response->headers->get('content-type'), 'text/html')) {
            return;
        }

        if (! method_exists($response, 'status') || $response->status() !== 200) {
            return;
        }

        $html = (string) $response->getContent();

        if (! str_contains($html, '</html>')) {
            return;
        }

        $head = static::printsStyles($html) ? '' : static::styles()->toHtml()."\n";
        $body = static::printsScripts($html) ? '' : static::scripts()->toHtml()."\n";

        if ($head === '' && $body === '') {
            return;
        }

        $original = $response->original;
        $response->setContent(static::place($html, $head, $body));
        $response->original = $original;
    }

    /**
     * @filamentStyles prints the colour palette as variables on :root.
     */
    protected static function printsStyles(string $html): bool
    {
        return str_contains($html, '--primary-500:');
    }

    /**
     * @filamentScripts prints the asset data before the script tags.
     */
    protected static function printsScripts(string $html): bool
    {
        return str_contains($html, 'window.filamentData');
    }

    protected static function place(string $html, string $head, string $body): string
    {
        if (preg_match('/<\s*\/\s*head\s*>/i', $html) && preg_match('/<\s*\/\s*body\s*>/i', $html)) {
            $html = preg_replace('/(<\s*\/\s*head\s*>)/i', $head.'$1', $html, 1);

            return preg_replace('/(<\s*\/\s*body\s*>)/i', $body.'$1', $html, 1);
        }

        $html = preg_replace('/(<\s*html(?:\s[^>]*)?>)/i', '$1'.$head, $html, 1);

        return preg_replace('/(<\s*\/\s*html\s*>)/i', $body.'$1', $html, 1);
    }
}
