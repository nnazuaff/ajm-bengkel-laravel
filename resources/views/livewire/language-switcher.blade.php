<div class="flex items-center gap-2 px-2 py-1">
    <div class="text-xs font-medium text-gray-500 dark:text-gray-400">
        {{ __('Language') }}:
    </div>
    <select 
        wire:model.live="currentLocale" 
        wire:change="switchLocale($event.target.value)"
        class="border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 focus:border-blue-500 focus:ring-blue-500 rounded-md shadow-sm text-xs p-1 cursor-pointer"
    >
        @foreach($locales as $code => $label)
            <option value="{{ $code }}" {{ $currentLocale === $code ? 'selected' : '' }}>
                {{ $label }}
            </option>
        @endforeach
    </select>
</div>
