<?php

use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Component;

new class extends Component
{
    public string $locale = '';

    public function mount(): void
    {
        $this->locale = Auth::user()->locale ?? (string) config('app.locale');
    }

    /**
     * Persiste el idioma de interfaz del usuario y recarga el perfil para que el
     * middleware SetLocale aplique el nuevo locale en toda la UI.
     */
    public function setLocale(string $locale): void
    {
        $this->locale = $locale;

        $soportados = array_keys((array) config('locales.supported', []));

        $this->validate([
            'locale' => ['required', 'string', 'in:'.implode(',', $soportados)],
        ]);

        Auth::user()->update(['locale' => $this->locale]);

        $this->redirect(route('profile'), navigate: true);
    }
}; ?>

<section>
    <p style="font-size:13px;color:var(--text-secondary);margin-bottom:16px;">
        {{ __('profile.language_hint') }}
    </p>

    <div role="group" aria-label="{{ __('profile.language_label') }}"
         style="display:inline-flex;border:1px solid var(--border);border-radius:8px;padding:4px;gap:4px;background:var(--bg-subtle);">
        @foreach(config('locales.supported') as $code => $label)
            <button type="button" wire:click="setLocale('{{ $code }}')"
                    @class([
                        'btn', 'btn-sm',
                        'btn-primary' => $locale === $code,
                        'btn-ghost' => $locale !== $code,
                    ])
                    @if($locale === $code) aria-current="true" @endif
                    style="min-width:96px;justify-content:center;">
                {{ $label }}
            </button>
        @endforeach
    </div>
</section>
