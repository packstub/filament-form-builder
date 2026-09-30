<x-dynamic-component :component="$layout" :title="$form->pageTitle()" :description="$form->pageDescription()" :image="$form->pageImage()" :slug="$form->slug">
    @if (! ($embed ?? false))
        @if ($form->pageLogo())
            <img class="fb-page__logo" src="{{ $form->pageLogo() }}" alt="">
        @endif
        <h1 class="fb-page__title">{{ $form->name }}</h1>
        @if ($form->description)
            <p class="fb-page__description">{{ $form->description }}</p>
        @endif
    @endif

    <x-form-builder::form :form="$form" :link="($shareLink ?? null)?->token" />
</x-dynamic-component>
