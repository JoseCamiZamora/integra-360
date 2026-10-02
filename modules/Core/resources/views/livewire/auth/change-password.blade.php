<x-core::auth-card :title="__('core::auth.change_password.title')" :intro="__('core::auth.change_password.intro')">
    <form wire:submit="save" class="flex flex-col gap-5" novalidate>
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
            {{ __('core::auth.change_password.submit') }}
        </button>
    </form>

    <x-core::logout-button />
</x-core::auth-card>
