<?php

namespace App\Livewire;

use Illuminate\Support\Facades\Session;
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

    public function switchLocale(string $locale): void
    {
        if (array_key_exists($locale, $this->locales)) {
            Session::put('locale', $locale);
            app()->setLocale($locale);
            $this->currentLocale = $locale;
            
            $this->dispatch('localeChanged', locale: $locale);
            
            // Refresh halaman untuk apply perubahan
            $this->redirect(request()->header('Referer') ?: '/admin');
        }
    }

    public function render()
    {
        return view('livewire.language-switcher');
    }
}
