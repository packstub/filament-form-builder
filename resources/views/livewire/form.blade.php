<div class="fb-form fb-form--livewire" id="form-{{ $model->slug }}">
    @if ($submitted)
        <div class="fb-success" role="status">{{ $message }}</div>
    @elseif ($error !== null)
        <div class="fb-closed" role="status">{{ $error }}</div>
    @else
        <form wire:submit="submit">
            {{ $this->form }}

            <div class="fb-actions">
                <x-filament::button type="submit" wire:loading.attr="disabled">
                    {{ $model->submitLabel() }}
                </x-filament::button>
            </div>
        </form>
    @endif
</div>
