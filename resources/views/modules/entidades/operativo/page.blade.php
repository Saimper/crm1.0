<x-app-layout>
    @php
        $proyecto = app('tenancy.proyecto_activo');
        $entidad = \Illuminate\Support\Facades\DB::table('entidades_configurables')
            ->where('id', $entidadId)
            ->where('proyecto_id', $proyecto->id)
            ->first();
    @endphp

    <div class="page">
        <div class="page-header">
            <div>
                <h1 class="page-title">{{ $entidad->nombre ?? 'Entidad' }}</h1>
                <div class="page-subtitle">{{ $proyecto->nombre }}</div>
            </div>
            <div style="display:flex;gap:8px;">
                <a href="{{ route('proyectos.dashboard', ['proyecto_id' => $proyecto->id]) }}"
                   wire:navigate class="btn btn-ghost btn-sm">← Volver al proyecto</a>
            </div>
        </div>

        <livewire:entidades.gestor-registros-entidad
            :proyecto-id="$proyectoId"
            :entidad-id="$entidadId" />
    </div>
</x-app-layout>
