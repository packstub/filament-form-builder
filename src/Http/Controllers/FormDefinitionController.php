<?php

namespace Packstub\FormBuilder\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Packstub\FormBuilder\FormBuilder;
use Packstub\FormBuilder\Submissions\ProtectionToken;
use Packstub\FormBuilder\Submissions\SpamGuard;

/**
 * The form definition for headless clients, with a fresh protection token
 * and the names of the anti-spam fields to send back.
 */
class FormDefinitionController
{
    public function __invoke(Request $request, string $form, ProtectionToken $tokens, SpamGuard $spam): JsonResponse
    {
        $form = FormBuilder::formModel()::query()->where('slug', $form)->firstOrFail();

        return response()->json([
            ...$form->toDefinition(),
            'protection' => [
                'token_field' => $tokens->field(),
                'token' => $tokens->make($form),
                'honeypot_field' => $form->usesHoneypot() ? $spam->honeypotField() : null,
            ],
        ])->header('Cache-Control', 'no-store');
    }
}
