<x-core::auth-card :title="__('core::auth.forgot.title')" :intro="__('core::auth.forgot.intro')">
    @if ($sent)
        <p role="status" class="flex items-start gap-1.5 rounded-control bg-success-bg p-3 text-base text-success">
            <span aria-hidden="true" class="font-mono font-semibold">✓</span>
            <span>{{ __('core::auth.forgot.sent') }}</span>
        </p>
    @else
        <form wire:submit="send" class="flex flex-col gap-5" novalidate>
            <x-core::field name="email"
                           type="email"
                           :label="__('core::auth.forgot.email')"
                           autocomplete="email"
                           inputmode="email" />

            <button type="submit" class="btn-primary" wire:loading.attr="disabled">
                {{ __('core::auth.forgot.submit') }}
            </button>
        </form>
    @endif

    <p class="text-base text-text-muted">{{ __('core::auth.forgot.no_email') }}</p>

    <a href="{{ route('login') }}" class="inline-flex items-center self-center text-base font-medium text-primary underline-offset-4 hover:underline">
        {{ __('core::auth.forgot.back') }}
    </a>
</x-core::auth-card>
