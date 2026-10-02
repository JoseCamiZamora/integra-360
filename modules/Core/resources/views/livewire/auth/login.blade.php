<x-core::auth-card :title="__('core::auth.login.heading')">
    @if (session('status'))
        <p role="status" class="flex items-start gap-1.5 rounded-control bg-success-bg p-3 text-base text-success">
            <span aria-hidden="true" class="font-mono font-semibold">✓</span>
            <span>{{ session('status') }}</span>
        </p>
    @endif

    <form wire:submit="authenticate" class="flex flex-col gap-5" novalidate>
        <x-core::field name="identifier"
                       :label="__('core::auth.login.identifier')"
                       :help="__('core::auth.login.identifier_help')"
                       autocomplete="username"
                       autocapitalize="none"
                       spellcheck="false" />

        <x-core::field name="password"
                       type="password"
                       :label="__('core::auth.login.password')"
                       autocomplete="current-password" />

        <label class="flex items-center gap-3 text-base text-text">
            <input type="checkbox" wire:model="remember" class="size-6 rounded-control border-border accent-primary">
            <span>{{ __('core::auth.login.remember') }}</span>
        </label>

        <button type="submit" class="btn-primary" wire:loading.attr="disabled">
            {{ __('core::auth.login.submit') }}
        </button>
    </form>

    <a href="{{ route('password.request') }}" class="inline-flex items-center self-center text-base font-medium text-primary underline-offset-4 hover:underline">
        {{ __('core::auth.login.forgot') }}
    </a>
</x-core::auth-card>
