@props(['name', 'label', 'type' => 'text', 'help' => null, 'autocomplete' => null, 'inputmode' => null])

{{-- Labelled input for the sign-in screens. 16 px text so iOS does not zoom. --}}
<div class="flex flex-col gap-1.5">
    <label for="{{ $name }}" class="text-base font-medium text-text">{{ $label }}</label>

    <input id="{{ $name }}"
           name="{{ $name }}"
           type="{{ $type }}"
           wire:model="{{ $name }}"
           @if ($autocomplete) autocomplete="{{ $autocomplete }}" @endif
           @if ($inputmode) inputmode="{{ $inputmode }}" @endif
           @if ($help) aria-describedby="{{ $name }}-help" @endif
           @error($name) aria-invalid="true" aria-errormessage="{{ $name }}-error" @enderror
           {{ $attributes->class([
               'h-touch rounded-control border bg-surface px-3 text-base text-text',
               'border-border' => ! $errors->has($name),
               'border-danger' => $errors->has($name),
           ]) }}>

    @if ($help)
        <p id="{{ $name }}-help" class="text-sm text-text-muted">{{ $help }}</p>
    @endif

    @error($name)
        <p id="{{ $name }}-error" role="alert" class="flex items-start gap-1.5 text-sm font-medium text-danger">
            <span aria-hidden="true" class="font-mono">✕</span>
            <span>{{ $message }}</span>
        </p>
    @enderror
</div>
