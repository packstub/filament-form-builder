<x-dynamic-component :component="$layout" :title="$form->name">
    <h1 class="fb-page__title">{{ $form->name }}</h1>
    @if ($form->description)
        <p class="fb-page__description">{{ $form->description }}</p>
    @endif

    <x-form-builder::form :form="$form" />
</x-dynamic-component>
