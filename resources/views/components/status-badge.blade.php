<span {{ $attributes->class([
    'inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-sm leading-none font-medium whitespace-nowrap',
    'bg-success-bg text-success' => $tone === \App\Support\Status\StatusTone::Success,
    'bg-warning-bg text-warning' => $tone === \App\Support\Status\StatusTone::Warning,
    'bg-danger-bg text-danger' => $tone === \App\Support\Status\StatusTone::Danger,
    'bg-neutral-bg text-neutral' => $tone === \App\Support\Status\StatusTone::Neutral,
]) }} data-tone="{{ $tone->value }}">
    <span aria-hidden="true" class="font-mono font-semibold">{{ $tone->symbol() }}</span>
    <span>{{ $text }}</span>
</span>
