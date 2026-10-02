<x-core::auth-card :title="__('core::auth.reset.title')">
    <form wire:submit="save" class="flex flex-col gap-5" novalidate>
        <x-core::field name="email"
                       type="email"
                       :label="__('core::auth.forgot.email')"
                       autocomplete="email"
                       inputmode="email" />

        <x-core::field name="password"
                       type="password"
                       :label="__('core::auth.change_password.password')"
                       :help="__('core::auth.change_password.hint')"
                       autocomplete="new-password" />

        <x-core::field name="password_confirmation"
                       type="password"
                       :label="__('core::auth.change_password.password_confirmation')"
                       autocomplete="new-password" />

        <button type="submit" class="btn-primary" wire:loading.attr="disabled">
            {{ __('core::auth.reset.submit') }}
        </button>
    </form>
</x-core::auth-card>
