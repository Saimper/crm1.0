<x-app-layout>
    @php $proyecto = app()->bound('tenancy.proyecto_activo') ? app('tenancy.proyecto_activo') : null; @endphp

    <div class="page">
        <div class="page-header">
            <div>
                <h1 class="page-title">{{ $proyecto ? 'Auditoría' : 'Auditoría global' }}</h1>
                <div class="page-subtitle">
                    {{ $proyecto ? $proyecto->nombre : 'Eventos de todos los proyectos + eventos globales (admin).' }}
                </div>
            </div>
            <div style="display:flex;gap:8px;">
                @if($proyecto)
                    <a href="{{ route('proyectos.dashboard', ['proyecto_id' => $proyecto->id]) }}"
                       wire:navigate class="btn btn-ghost btn-sm">← Volver al proyecto</a>
                @else
                    <a href="{{ route('admin.dashboard') }}" wire:navigate class="btn btn-ghost btn-sm">← Panel admin</a>
                @endif
            </div>
        </div>

        <livewire:auditoria.listado-auditoria />
    </div>
</x-app-layout>
