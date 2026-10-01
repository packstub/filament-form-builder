<?php

namespace Packstub\FormBuilder\Mail;

use Illuminate\Mail\Mailable;
use Packstub\FormBuilder\Models\Form;

/**
 * The form's logo and brand colour (Design tab) on the emails: the logo in
 * the header, the colour on buttons and links through a theme that extends
 * the app's mail theme. Without either, the emails look as before.
 */
final class Branding
{
    public const THEME = 'packstub-form-builder::mail.theme';

    /**
     * The brand colour as a hex value, or null (anything else is ignored,
     * so the theme never prints untrusted CSS).
     */
    public static function color(Form $form): ?string
    {
        $color = $form->brandColor();

        return is_string($color) && preg_match('/^#(?:[0-9a-f]{3}|[0-9a-f]{6})$/i', trim($color)) ? trim($color) : null;
    }

    public static function logo(Form $form): ?string
    {
        $logo = $form->pageLogo();

        return is_string($logo) && preg_match('~^https?://~i', $logo) && filter_var($logo, FILTER_VALIDATE_URL) ? $logo : null;
    }

    /**
     * Switch the mail to the branded theme when the form has a colour.
     */
    public static function apply(Mailable $mail, Form $form): void
    {
        if (self::color($form) !== null) {
            $mail->theme = self::THEME;
        }
    }

    /**
     * @return array{brand: ?string, logo: ?string}
     */
    public static function viewData(Form $form): array
    {
        return ['brand' => self::color($form), 'logo' => self::logo($form)];
    }

    /**
     * The mail theme the branded one builds on: the app's configured theme.
     */
    public static function baseTheme(): string
    {
        $theme = (string) config('mail.markdown.theme', 'default');

        if (view()->exists('mail.'.$theme)) {
            return 'mail.'.$theme;
        }

        return str_contains($theme, '::') ? $theme : 'mail::themes.'.$theme;
    }

    /**
     * Whether the markdown mail is rendering its HTML part (the "mail"
     * view namespace points at the html components) rather than the text
     * one, so the header prints the logo image only where it belongs.
     */
    public static function rendersHtml(): bool
    {
        foreach (app('view')->getFinder()->getHints()['mail'] ?? [] as $path) {
            if (str_ends_with(rtrim(str_replace('\\', '/', (string) $path), '/'), '/text')) {
                return false;
            }
        }

        return true;
    }
}
