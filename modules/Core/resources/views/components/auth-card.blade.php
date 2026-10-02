@props(['title', 'intro' => null])

{{-- Shell of the sign-in screens: mobile-first (390 px), dark header like the driver view. --}}
<div class="mx-auto flex min-h-dvh w-full max-w-md flex-col">
    <header class="bg-sidebar px-4 pb-6 pt-[max(1.5rem,env(safe-area-inset-top))] text-sidebar-text sm:mt-8 sm:rounded-t-card sm:px-6">
        <div class="flex items-center gap-2">
            <span aria-hidden="true"
                  class="flex size-8 items-center justify-center rounded-control bg-primary font-mono text-xs font-semibold">360</span>
            <span class="text-sm font-semibold">{{ config('app.name') }}</span>
        </div>

        <h1 class="mt-5 text-2xl font-semibold">{{ $title }}</h1>

        @if ($intro)
            <p class="mt-1 text-base text-sidebar-text-muted">{{ $intro }}</p>
        @endif
    </header>

    <main class="flex flex-1 flex-col gap-5 bg-surface p-4 sm:mb-8 sm:rounded-b-card sm:border sm:border-t-0 sm:border-border sm:p-6">
        {{ $slot }}
    </main>
</div>
