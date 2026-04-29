<x-app-layout>
    <x-slot name="header">
        @php $proyecto = app('tenancy.proyecto_activo'); @endphp
        <x-ui.page-header
            title="Usuarios del proyecto"
            :subtitle="$proyecto->nombre"
            :back="route('proyectos.dashboard', ['proyecto_id' => $proyecto->id])"
            back-label="← Volver al proyecto" />
    </x-slot>

    <div class="py-6">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8">
            <livewire:usuarios.gestion-usuarios-proyecto />
        </div>
    </div>
</x-app-layout>
