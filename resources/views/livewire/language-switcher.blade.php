<div class="flex items-center gap-2">
    <select 
        wire:model.live="currentLocale" 
        wire:change="switchLocale($event.target.value)"
        class="border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-blue-500 focus:ring-blue-500 rounded-md shadow-sm text-sm"
    >
        @foreach($locales as $code => $label)
            <option value="{{ $code }}" {{ $currentLocale === $code ? 'selected' : '' }}>
                {{ $label }}
            </option>
        @endforeach
    </select>
</div>
