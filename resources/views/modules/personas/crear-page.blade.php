<x-app-layout>
    @php $proyecto = app('tenancy.proyecto_activo'); @endphp

    <div class="page">
        <div class="page-header">
            <div>
                <h1 class="page-title">{{ __('personas.title_create') }}</h1>
                <div class="page-subtitle">{{ $proyecto->nombre }}</div>
            </div>
            <div style="display:flex;gap:8px;align-items:center;">
                <span class="text-xs text-ink-500 font-mono">{{ $proyecto->codigo }}</span>
                <a href="{{ route('proyectos.dashboard', ['proyecto_id' => $proyecto->id]) }}"
                   wire:navigate class="btn btn-ghost btn-sm">{{ __('personas.back_project') }}</a>
            </div>
        </div>

        <livewire:personas.crear-persona />
    </div>
</x-app-layout>
