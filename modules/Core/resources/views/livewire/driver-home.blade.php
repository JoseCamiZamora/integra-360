<div class="mx-auto flex min-h-dvh w-full max-w-3xl flex-col">
    <header class="bg-sidebar px-4 pb-6 pt-[max(1rem,env(safe-area-inset-top))] text-sidebar-text sm:px-6">
        <div class="flex items-center justify-between gap-3">
            <div class="flex min-w-0 items-center gap-2">
                <span aria-hidden="true"
                      class="flex size-8 shrink-0 items-center justify-center rounded-control bg-primary font-mono text-xs font-semibold">360</span>
                <span class="truncate text-sm font-semibold">{{ $company->displayName() }}</span>
            </div>

            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="inline-flex items-center rounded-control px-3 text-sm font-medium text-sidebar-text underline-offset-4 hover:underline">
                    {{ __('core::auth.logout') }}
                </button>
            </form>
        </div>

        <h1 class="mt-5 text-2xl font-semibold">{{ __('core::driver.greeting', ['name' => $firstName]) }}</h1>
        <p class="mt-1 text-base text-sidebar-text-muted">
            <time datetime="{{ now()->toDateString() }}">{{ $today }}</time>
        </p>
    </header>

    <main class="flex flex-1 flex-col gap-4 p-4 sm:p-6">
        {{-- Vehicle card and "Iniciar inspección" button (PESV prompts). --}}
        <section aria-label="{{ __('core::driver.content_label') }}"
                 class="rounded-card border border-border bg-surface p-4 text-text-muted">
            <p>{{ __('core::driver.empty') }}</p>
        </section>

        @if ($panelUrl)
            <a href="{{ $panelUrl }}" class="inline-flex items-center self-start text-base font-medium text-primary underline-offset-4 hover:underline">
                {{ __('core::auth.switch_to_panel') }}
            </a>
        @endif
    </main>
</div>
