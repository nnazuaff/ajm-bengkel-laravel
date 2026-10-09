@props(['inline' => []])
@php($remainingErrors = collect($errors->getMessages())->except($inline)->flatten()->unique())
@if ($remainingErrors->isNotEmpty())
    <div role="alert" class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800 dark:border-red-800 dark:bg-red-950 dark:text-red-200">
        <ul class="list-inside list-disc">
            @foreach ($remainingErrors as $message)<li>{{ $message }}</li>@endforeach
        </ul>
    </div>
@endif
