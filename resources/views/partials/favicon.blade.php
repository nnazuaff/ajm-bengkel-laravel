@php($faviconUrl = \App\Models\WorkshopSetting::current()->faviconUrl())
@if ($faviconUrl)
    <link rel="icon" href="{{ $faviconUrl }}" type="image/png">
    <link rel="apple-touch-icon" href="{{ $faviconUrl }}">
@else
    <link rel="icon" href="/favicon.ico" sizes="any">
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png">
@endif
