<x-app-layout>
    @php $proyecto = app('tenancy.proyecto_activo'); @endphp

    <div class="page">
        <div class="page-header">
            <div>
                <h1 class="page-title">{{ __('asignaciones.page_bandeja_title') }}</h1>
                <div class="page-subtitle">{{ $proyecto->nombre }}</div>
            </div>
            <div style="display:flex;gap:8px;align-items:center;">
                <span class="text-xs text-ink-500 font-mono">{{ $proyecto->codigo }}</span>
            </div>
        </div>

        <livewire:asignaciones.bandeja />
    </div>
</x-app-layout>
