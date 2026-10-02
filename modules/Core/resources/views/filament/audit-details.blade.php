{{-- Before/after values of one audit entry. Passwords are never logged. --}}
<div class="flex flex-col gap-4 text-sm">
    @if ($changes !== [])
        <div class="overflow-x-auto">
            <table class="w-full text-left">
                <thead>
                    <tr class="border-b border-border text-text-muted">
                        <th class="py-2 pe-3 font-medium">{{ __('core::audit.fields.changes') }}</th>
                        <th class="py-2 pe-3 font-medium">{{ __('core::audit.fields.before') }}</th>
                        <th class="py-2 font-medium">{{ __('core::audit.fields.after') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($changes as $attribute => [$before, $after])
                        <tr class="border-b border-border align-top">
                            <td class="py-2 pe-3 font-mono">{{ $attribute }}</td>
                            <td class="py-2 pe-3">{{ $before }}</td>
                            <td class="py-2">{{ $after }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    @if ($properties !== [])
        <dl class="grid grid-cols-[auto_1fr] gap-x-3 gap-y-1">
            @foreach ($properties as $key => $value)
                <dt class="font-mono text-text-muted">{{ $key }}</dt>
                <dd>{{ $value }}</dd>
            @endforeach
        </dl>
    @endif
</div>
