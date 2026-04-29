<x-app-layout>
    <x-slot name="header">
        @php
            $proyecto = app('tenancy.proyecto_activo');
            $entidad = \Illuminate\Support\Facades\DB::table('entidades_configurables')
                ->where('id', $entidadId)
                ->where('proyecto_id', $proyecto->id)
                ->first();
        @endphp
        <x-ui.page-header
            :title="$entidad->nombre ?? 'Entidad'"
            :subtitle="$proyecto->nombre"
            :back="route('proyectos.dashboard', ['proyecto_id' => $proyecto->id])"
            back-label="← Volver al proyecto" />
    </x-slot>

    <div class="py-6">
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8">
            <livewire:entidades.gestor-registros-entidad
                :proyecto-id="$proyectoId"
                :entidad-id="$entidadId" />
        </div>
    </div>
</x-app-layout>
