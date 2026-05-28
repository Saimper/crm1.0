<x-app-layout>
    @php $proyecto = app('tenancy.proyecto_activo'); @endphp

    <div class="page">
        <div class="page-header">
            <div>
                <h1 class="page-title">{{ __('casos.title_work') }}</h1>
                <div class="page-subtitle">{{ $proyecto->nombre }}</div>
            </div>
            <div style="display:flex;gap:8px;align-items:center;">
                <span class="font-mono" style="font-size:11px;color:var(--text-tertiary);">{{ $proyecto->codigo }}</span>
                <a href="{{ route('proyectos.bandeja', ['proyecto_id' => $proyecto->id]) }}"
                   wire:navigate class="btn btn-ghost btn-sm">{{ __('casos.back_to_tray') }}</a>
            </div>
        </div>

        <livewire:casos.vista-de-trabajo :persona="$persona" :caso="$caso ?? null" />
    </div>
</x-app-layout>
