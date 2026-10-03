{{-- Files of an expiring document: each link goes through the authorized
     download route, which redirects to a short-lived signed URL. --}}
<ul class="divide-y divide-gray-200 rounded-lg border border-gray-200">
    @foreach ($files as $file)
        <li class="flex min-h-11 items-center justify-between gap-3 px-3 py-2">
            <span class="min-w-0">
                <span class="block truncate font-medium">{{ $file['name'] }}</span>
                <span class="text-sm text-gray-500">{{ $file['size'] }}</span>
            </span>

            @if ($file['url'])
                <a href="{{ $file['url'] }}" target="_blank" rel="noopener"
                   class="inline-flex min-h-11 shrink-0 items-center font-medium text-primary-600 underline-offset-4 hover:underline">
                    {{ __('core::documents.actions.open_file') }}
                </a>
            @else
                <span class="shrink-0 text-sm text-gray-500">{{ __('core::documents.sensitive_file') }}</span>
            @endif
        </li>
    @endforeach
</ul>
