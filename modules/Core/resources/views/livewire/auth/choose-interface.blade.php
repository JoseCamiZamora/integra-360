<x-core::auth-card :title="__('core::auth.choose_interface.title')" :intro="__('core::auth.choose_interface.intro')">
    <div class="flex flex-col gap-3">
        <button type="button" wire:click="choose('driver')"
                class="flex min-h-touch-primary w-full flex-col items-start justify-center rounded-card bg-primary px-4 py-3 text-left text-white">
            <span class="text-lg font-semibold">{{ __('core::auth.choose_interface.driver') }}</span>
            <span class="text-sm">{{ __('core::auth.choose_interface.driver_help') }}</span>
        </button>

        <button type="button" wire:click="choose('panel')"
                class="flex min-h-touch-primary w-full flex-col items-start justify-center rounded-card border border-border bg-surface px-4 py-3 text-left hover:border-primary">
            <span class="text-lg font-semibold text-text">{{ __('core::auth.choose_interface.panel') }}</span>
            <span class="text-sm text-text-muted">{{ __('core::auth.choose_interface.panel_help') }}</span>
        </button>
    </div>

    <x-core::logout-button />
</x-core::auth-card>
