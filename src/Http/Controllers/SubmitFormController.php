<?php

namespace Packstub\FormBuilder\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Packstub\FormBuilder\Exceptions\FormClosedException;
use Packstub\FormBuilder\FormBuilder;
use Packstub\FormBuilder\Http\FormState;
use Packstub\FormBuilder\Models\Form;
use Packstub\FormBuilder\Submissions\SpamGuard;
use Packstub\FormBuilder\Submissions\SubmissionContext;
use Packstub\FormBuilder\Submissions\SubmissionResult;
use Packstub\FormBuilder\Submissions\Submitter;

class SubmitFormController
{
    public function __invoke(Request $request, string $form, Submitter $submitter): JsonResponse|RedirectResponse
    {
        $form = FormBuilder::formModel()::query()->where('slug', $form)->firstOrFail();
        $wantsJson = $request->expectsJson();
        $context = SubmissionContext::fromRequest($request, $wantsJson ? 'json' : 'web');

        try {
            $result = $submitter->submit($form, $request->all(), $context);
        } catch (FormClosedException $e) {
            return $this->failure($request, $form, ['form' => [$e->getMessage()]], 403);
        } catch (ValidationException $e) {
            return $this->failure($request, $form, $e->errors(), 422);
        }

        return $this->success($request, $form, $result);
    }

    protected function success(Request $request, Form $form, SubmissionResult $result): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            return response()->json($result->toArray());
        }

        if ($result->redirectUrl() !== null) {
            return redirect()->to($result->redirectUrl());
        }

        $back = $this->returnUrl($request, $form);

        if ($request->hasSession()) {
            return redirect()->to($this->anchored($back, $form))->with(FormState::successKey($form), $result->message());
        }

        return redirect()->to($this->anchored($this->withQuery($back, ['fb_success' => $form->slug]), $form));
    }

    /**
     * @param  array<string, array<int, string>>  $errors
     */
    protected function failure(Request $request, Form $form, array $errors, int $status): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            return response()->json([
                'ok' => false,
                'message' => $errors['form'][0] ?? __('packstub-form-builder::form-builder.frontend.invalid'),
                'errors' => $errors,
            ], $status);
        }

        $back = $this->returnUrl($request, $form);
        $input = $request->except(app(SpamGuard::class)->reservedKeys());

        if ($request->hasSession()) {
            return redirect()->to($this->anchored($back, $form))
                ->withErrors($errors, FormState::errorBag($form))
                ->withInput($input);
        }

        return redirect()->to($this->anchored($this->withQuery($back, [
            'fb_state' => FormState::encode($form, $errors, $input),
        ]), $form));
    }

    /**
     * The page to go back to: the form's hidden return field when it is on
     * this host, else the referer, else the hosted page.
     */
    protected function returnUrl(Request $request, Form $form): string
    {
        foreach ([$request->input('_fb_return'), $request->headers->get('referer')] as $candidate) {
            if (is_string($candidate) && $candidate !== '' && $this->sameHost($request, $candidate)) {
                return $this->withQuery($candidate, ['fb_success' => null, 'fb_state' => null]);
            }
        }

        return $form->pageUrl() ?? $request->root();
    }

    protected function sameHost(Request $request, string $url): bool
    {
        $host = parse_url($url, PHP_URL_HOST);

        return $host === null || $host === false || strcasecmp((string) $host, $request->getHost()) === 0;
    }

    /**
     * @param  array<string, ?string>  $params  A null value removes the parameter.
     */
    protected function withQuery(string $url, array $params): string
    {
        $url = preg_replace('/#.*$/', '', $url) ?? $url;
        [$base, $query] = array_pad(explode('?', $url, 2), 2, '');

        parse_str($query, $existing);

        foreach ($params as $key => $value) {
            if ($value === null) {
                unset($existing[$key]);
            } else {
                $existing[$key] = $value;
            }
        }

        return $existing === [] ? $base : $base.'?'.http_build_query($existing);
    }

    protected function anchored(string $url, Form $form): string
    {
        return $url.'#form-'.$form->slug;
    }
}
