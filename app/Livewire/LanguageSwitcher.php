<?php

namespace App\Livewire;

use Livewire\Component;

class LanguageSwitcher extends Component
{
    public string $currentLocale;

    public array $locales = [
        'id' => '🇮🇩 Indonesia',
        'en' => '🇬🇧 English',
    ];

    public function mount(): void
    {
        $this->currentLocale = app()->getLocale();
    }

    public function switchLocale($locale): void
    {
        if (array_key_exists($locale, $this->locales)) {
            session()->put('locale', $locale);
            app()->setLocale($locale);
            $this->currentLocale = $locale;

            // Force refresh via window.location for absolute locale change
            $this->js('window.location.reload()');
        }
    }

    public function render()
    {
        return view('livewire.language-switcher');
    }
}
