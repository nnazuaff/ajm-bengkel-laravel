@props(['sidebar' => false])

@php($horizontalLogoUrl = \App\Models\WorkshopSetting::current()->horizontalLogoUrl())

<a {{ $attributes->class(['flex min-w-0 shrink-0 items-center rounded-md focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-zinc-500', 'w-40 max-w-full' => $sidebar, 'w-44 max-w-full' => ! $sidebar]) }} data-workshop-brand>
    @if ($horizontalLogoUrl)
        <img src="{{ $horizontalLogoUrl }}" alt="AJM Bengkel" width="1600" height="560" class="h-auto w-full object-contain" />
    @else
        <span class="text-sm font-semibold">AJM Bengkel</span>
    @endif
</a>
