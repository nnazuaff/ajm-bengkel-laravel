@php
    $availableLocales = [
        'id' => '🇮🇩 Indonesia',
        'en' => '🇬🇧 ' . __('English'),
    ];

    $currentLocale = app()->getLocale();
@endphp

<div class="flex items-center gap-2 px-2 py-1">
    <label for="admin-language" class="text-xs font-medium text-gray-500 dark:text-gray-400">
        {{ __('Language') }}
    </label>

    <select
        id="admin-language"
        aria-label="{{ __('Language') }}"
        onchange="window.location.assign(this.value)"
        class="rounded-md border-gray-300 bg-white p-1 text-xs shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200"
    >
        @foreach ($availableLocales as $code => $label)
            <option value="{{ route('locale.switch', ['locale' => $code]) }}" @selected($currentLocale === $code)>
                {{ $label }}
            </option>
        @endforeach
    </select>
</div>
