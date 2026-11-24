<?php

namespace Packstub\FormBuilder\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Packstub\FormBuilder\Exceptions\FormClosedException;
use Packstub\FormBuilder\FormBuilder;
use Packstub\FormBuilder\Submissions\PasswordGate;
use Packstub\FormBuilder\Submissions\SubmissionContext;
use Packstub\FormBuilder\Submissions\Submitter;

/**
 * Validate part of a form without submitting it: the step of a multi-step
 * form the visitor wants to leave. Takes the values plus "_fb_fields", the
 * keys to check (or "_fb_step", the index of a section); answers
 * { ok: true } or 422 with the errors keyed by field, or 403 when the form
 * would not take a submission from this visitor (closed, private, locked).
 * Nothing is stored, uploaded files included.
 */
class ValidateFormController
{
    public function __invoke(Request $request, string $form, Submitter $submitter, PasswordGate $gate): JsonResponse
    {
        $form = FormBuilder::formModel()::query()->where('slug', $form)->firstOrFail();
        $input = $request->all();

        if ($form->password() !== null && ! isset($input[PasswordGate::FIELD]) && $request->hasSession()) {
            $input[PasswordGate::FIELD] = $request->session()->get($gate->sessionKey($form));
        }

        try {
            $submitter->assertCanPost($form, $input, SubmissionContext::fromRequest($request, 'json'));
        } catch (FormClosedException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage(), 'errors' => ['form' => [$e->getMessage()]]], 403);
        }

        $keys = $request->input('_fb_fields');

        if (! is_array($keys) && $request->filled('_fb_step')) {
            $section = $form->sections()->get((int) $request->input('_fb_step'));
            $keys = $section?->fields->filter(fn ($field): bool => $field->isInput())->map(fn ($field): string => $field->key)->values()->all() ?? [];
        }

        $keys = is_array($keys) ? array_values(array_filter(array_map('strval', $keys))) : null;

        try {
            $submitter->validate($form, $input, $keys, store: false);
        } catch (ValidationException $e) {
            return response()->json([
                'ok' => false,
                'message' => __('packstub-form-builder::form-builder.frontend.invalid'),
                'errors' => $e->errors(),
            ], 422);
        }

        return response()->json(['ok' => true]);
    }
}
