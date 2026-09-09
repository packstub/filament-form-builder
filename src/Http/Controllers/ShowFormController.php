<?php

namespace Packstub\FormBuilder\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Packstub\FormBuilder\FormBuilder;

/**
 * The hosted page: the form on its own, in the configured layout.
 */
class ShowFormController
{
    public function __invoke(Request $request, string $form): View
    {
        $form = FormBuilder::formModel()::query()->where('slug', $form)->firstOrFail();

        return view('packstub-form-builder::pages.show', [
            'form' => $form,
            'layout' => config('packstub-form-builder.routes.page_layout', 'packstub-form-builder::layout'),
        ]);
    }
}
