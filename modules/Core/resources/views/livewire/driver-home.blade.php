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
        @if ($vehicle)
            <section aria-labelledby="vehicle-heading" class="rounded-card border border-border bg-surface p-4">
                <p id="vehicle-heading" class="text-sm font-medium text-text-muted">{{ __('core::driver.vehicle.heading') }}</p>

                <p class="mt-1 font-mono text-3xl font-semibold tracking-wider text-text" data-plate>{{ $vehicle->plate }}</p>
                <p class="mt-1 text-base text-text">
                    {{ $vehicle->vehicle_type->label() }} · {{ $vehicle->brand }} {{ $vehicle->model_line }}
                </p>

                <dl class="mt-4 flex flex-col gap-3">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <dt class="text-base text-text-muted">{{ __('core::driver.vehicle.documents') }}</dt>
                        <dd><x-status-badge :status="$vehicleReport->status" /></dd>
                    </div>
                    @if ($personReport)
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <dt class="text-base text-text-muted">{{ __('core::driver.vehicle.my_documents') }}</dt>
                            <dd><x-status-badge :status="$personReport->status" /></dd>
                        </div>
                    @endif
                </dl>

                @php($items = [...$pending($vehicleReport), ...($personReport ? $pending($personReport) : [])])
                @if ($items !== [])
                    <ul class="mt-4 list-disc space-y-1 pl-5 text-base text-text">
                        @foreach ($items as $item)
                            <li>{{ $item }}</li>
                        @endforeach
                    </ul>
                    <p class="mt-2 text-sm text-text-muted">{{ __('core::driver.documents.tell_admin') }}</p>
                @endif
            </section>
        @else
            <section aria-label="{{ __('core::driver.content_label') }}"
                     class="rounded-card border border-border bg-surface p-4">
                <p class="text-base text-text">{{ __('core::driver.vehicle.none') }}</p>

                @if ($personReport && ! $personReport->isCompliant())
                    <div class="mt-3 flex flex-wrap items-center justify-between gap-2">
                        <span class="text-base text-text-muted">{{ __('core::driver.vehicle.my_documents') }}</span>
                        <x-status-badge :status="$personReport->status" />
                    </div>
                @endif
            </section>
        @endif

        {{-- Enabled by the PESV pre-trip inspection (I360-05). --}}
        <div>
            <button type="button" class="btn-primary cursor-not-allowed opacity-60" disabled aria-describedby="inspection-help">
                {{ __('core::driver.inspection.button') }}
            </button>
            <p id="inspection-help" class="mt-2 text-sm text-text-muted">{{ __('core::driver.inspection.unavailable') }}</p>
        </div>

        @if ($panelUrl)
            <a href="{{ $panelUrl }}" class="inline-flex items-center self-start text-base font-medium text-primary underline-offset-4 hover:underline">
                {{ __('core::auth.switch_to_panel') }}
            </a>
        @endif
    </main>
</div>
