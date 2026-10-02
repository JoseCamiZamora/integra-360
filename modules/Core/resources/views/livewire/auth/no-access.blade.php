<x-core::auth-card :title="__('core::auth.no_access.title')">
    <p class="flex items-start gap-1.5 rounded-control bg-warning-bg p-3 text-base text-warning">
        <span aria-hidden="true" class="font-mono font-semibold">!</span>
        <span>{{ __('core::auth.no_access.body') }}</span>
    </p>

    <x-core::logout-button />
</x-core::auth-card>
