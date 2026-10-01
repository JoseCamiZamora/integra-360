<div class="mx-auto flex min-h-dvh w-full max-w-3xl flex-col">
    <header class="bg-sidebar px-4 pb-6 pt-[max(1rem,env(safe-area-inset-top))] text-sidebar-text sm:px-6">
        <div class="flex items-center gap-2">
            <span aria-hidden="true"
                  class="flex size-8 items-center justify-center rounded-control bg-primary font-mono text-xs font-semibold">360</span>
            <span class="text-sm font-semibold">{{ config('app.name') }}</span>
        </div>

        <h1 class="mt-5 text-2xl font-semibold">{{ __('core::driver.greeting') }}</h1>
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
    </main>
</div>
