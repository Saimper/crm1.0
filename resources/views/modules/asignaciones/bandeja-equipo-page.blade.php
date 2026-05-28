<x-app-layout>
    @php $proyecto = app('tenancy.proyecto_activo'); @endphp

    <div class="page">
        <div class="page-header">
            <div>
                <h1 class="page-title">{{ __('asignaciones.page_bandeja_equipo_title') }}</h1>
                <div class="page-subtitle">{{ $proyecto->nombre }}</div>
            </div>
            <div style="display:flex;gap:8px;">
                <a href="{{ route('proyectos.dashboard', ['proyecto_id' => $proyecto->id]) }}"
                   wire:navigate class="btn btn-ghost btn-sm">{{ __('asignaciones.back_to_project') }}</a>
            </div>
        </div>

        <livewire:asignaciones.bandeja-equipo />
    </div>
</x-app-layout>
