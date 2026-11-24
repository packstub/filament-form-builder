@php
    $customCss = $model->inlineCss();
    $brand = $model->brandColor();
@endphp
<div class="fb-form fb-form--livewire {{ $model->layout() === 'horizontal' ? 'fb-form--horizontal' : '' }}" id="form-{{ $model->slug }}" data-fb-form="{{ $model->slug }}" @if ($brand) style="--fb-color-primary: {{ $brand }}" @endif>
    @if ($customCss)
        <style>{!! $customCss !!}</style>
    @endif

    @if ($submitted)
        <div class="fb-success" role="status">{{ $message }}</div>
    @elseif ($error !== null)
        <div class="fb-closed" role="status">{{ $error }}</div>
    @elseif ($locked)
        <form wire:submit="unlock" class="fb-form__unlock">
            <p class="fb-paragraph" style="margin-bottom: 1rem">{{ __('packstub-form-builder::form-builder.frontend.password_prompt') }}</p>
            <x-filament::input.wrapper :valid="$passwordError === null">
                <x-filament::input type="password" wire:model="password" :placeholder="__('packstub-form-builder::form-builder.frontend.password_label')" autocomplete="off" />
            </x-filament::input.wrapper>
            @if ($passwordError)
                <p class="fb-error" style="margin-top: .5rem">{{ $passwordError }}</p>
            @endif
            <div class="fb-actions" style="margin-top: 1rem">
                <x-filament::button type="submit" wire:loading.attr="disabled">
                    {{ __('packstub-form-builder::form-builder.frontend.unlock') }}
                </x-filament::button>
            </div>
        </form>
    @else
        <form
            @if ($captcha)
                x-data
                x-on:submit.prevent="$wire.submit($el.querySelector('[name$=-response]')?.value ?? null)"
            @else
                wire:submit="submit"
            @endif
        >
            {{ $this->form }}

            @if ($captcha)
                <div class="fb-field fb-field--captcha" style="margin-top: 1rem" wire:ignore>
                    {!! $captcha['html'] !!}
                </div>
                @error('captcha')
                    <p class="fb-error" style="margin-top: .5rem">{{ $message }}</p>
                @enderror
                <script src="{{ $captcha['script'] }}" async defer></script>
            @endif

            @error('form')
                <p class="fb-error" role="alert" style="margin-top: 1rem">{{ $message }}</p>
            @enderror

            @unless ($model->isWizard())
                <div class="fb-actions" style="margin-top: 1.5rem">
                    <x-filament::button type="submit" wire:loading.attr="disabled">
                        {{ $model->submitLabel() }}
                    </x-filament::button>
                </div>
            @endunless
        </form>
    @endif

    @if ($model->customJs() && ! $submitted && $error === null && ! $locked)
        <script>(function (form) { {!! $model->inlineJs() !!} })(document.getElementById(@json('form-'.$model->slug)));</script>
    @endif
</div>
