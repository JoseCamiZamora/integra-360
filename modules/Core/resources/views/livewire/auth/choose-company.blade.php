<x-core::auth-card :title="__('core::auth.choose_company.title')" :intro="__('core::auth.choose_company.intro')">
    <ul class="flex flex-col gap-3">
        @foreach ($companies as $company)
            <li>
                <button type="button"
                        wire:click="choose('{{ $company->getKey() }}')"
                        class="flex min-h-touch-primary w-full flex-col items-start justify-center rounded-card border border-border bg-surface px-4 py-3 text-left hover:border-primary">
                    <span class="text-lg font-semibold text-text">{{ $company->displayName() }}</span>
                    <span class="font-mono text-sm text-text-muted">NIT {{ $company->nit }}</span>
                </button>
            </li>
        @endforeach
    </ul>

    <x-core::logout-button />
</x-core::auth-card>
