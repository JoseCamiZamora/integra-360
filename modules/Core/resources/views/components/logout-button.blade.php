{{-- Plain form POST: works without JavaScript. --}}
<form method="POST" action="{{ route('logout') }}" {{ $attributes->class(['self-center']) }}>
    @csrf
    <button type="submit" class="inline-flex items-center text-base font-medium text-primary underline-offset-4 hover:underline">
        {{ __('core::auth.logout') }}
    </button>
</form>
