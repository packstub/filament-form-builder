<?php

namespace Packstub\FormBuilder\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Packstub\FormBuilder\FormBuilder;
use Packstub\FormBuilder\Models\ShareLink;
use Packstub\FormBuilder\Submissions\Captcha;
use Packstub\FormBuilder\Submissions\ProtectionToken;
use Packstub\FormBuilder\Submissions\SpamGuard;

/**
 * The form definition for headless clients, with a fresh protection token
 * and the names of the anti-spam fields to send back. A private form needs
 * a signed URL or "?link=" with an active share link's token (send it back
 * as "_fb_link" with the submission).
 */
class FormDefinitionController
{
    public function __invoke(Request $request, string $form, ProtectionToken $tokens, SpamGuard $spam): JsonResponse
    {
        $form = FormBuilder::formModel()::query()->where('slug', $form)->firstOrFail();

        $link = ShareLink::findActive($form, $request->query('link'));

        if ($form->isPrivate() && ! $request->hasValidSignature() && $link === null) {
            abort(403, __('packstub-form-builder::form-builder.frontend.private'));
        }

        return response()->json([
            ...$form->toDefinition(),
            'protection' => [
                'token_field' => $tokens->field(),
                'token' => $tokens->make($form, link: $link?->token),
                'honeypot_field' => $form->usesHoneypot() ? $spam->honeypotField() : null,
                'password' => $form->password() !== null,
                'unlock_url' => $form->password() !== null ? route('packstub-form-builder.unlock', $form) : null,
                'captcha' => $form->captcha() !== null ? ['provider' => $form->captcha(), 'site_key' => Captcha::siteKey($form->captcha()), 'field' => Captcha::responseField($form->captcha())] : null,
            ],
        ])->header('Cache-Control', 'no-store');
    }
}
