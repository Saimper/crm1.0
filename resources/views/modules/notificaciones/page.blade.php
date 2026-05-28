<x-app-layout>
    @php $proyecto = app('tenancy.proyecto_activo'); @endphp

    <div class="page">
        <div class="page-header">
            <div>
                <h1 class="page-title">{{ __('notificaciones.title') }}</h1>
                <div class="page-subtitle">{{ $proyecto->nombre }}</div>
            </div>
            <div style="display:flex;gap:8px;">
                <a href="{{ route('proyectos.dashboard', ['proyecto_id' => $proyecto->id]) }}"
                   wire:navigate class="btn btn-ghost btn-sm">{{ __('notificaciones.back_to_project') }}</a>
            </div>
        </div>

        <livewire:notificaciones.listado-notificaciones />
    </div>
</x-app-layout>
