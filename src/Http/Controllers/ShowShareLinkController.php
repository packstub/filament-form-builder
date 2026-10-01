<?php

namespace Packstub\FormBuilder\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Packstub\FormBuilder\FormBuilder;
use Packstub\FormBuilder\Http\FormState;

/**
 * GET /f/{token}: a private (or public) form through one of its share links,
 * on the hosted page; "?embed=1" renders the bare layout for an iframe. A
 * revoked, expired or full link answers 403 with the reason, except right
 * after a submission (the one that filled the link shows its success message).
 */
class ShowShareLinkController
{
    public function __invoke(Request $request, string $token): View
    {
        $link = FormBuilder::shareLinkModel()::query()->where('token', $token)->first();
        $form = $link?->form;

        abort_if($form === null, 404);

        if (($reason = $link->closedReason()) !== null && ! FormState::for($form, $request)->success) {
            abort(403, $reason);
        }

        $embed = $request->boolean('embed');

        return view('packstub-form-builder::pages.show', [
            'form' => $form,
            'embed' => $embed,
            'shareLink' => $link,
            'layout' => $embed ? 'packstub-form-builder::embed-layout' : config('packstub-form-builder.routes.page_layout', 'packstub-form-builder::layout'),
        ]);
    }
}
