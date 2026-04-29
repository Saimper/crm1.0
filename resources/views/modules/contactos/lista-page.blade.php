<x-app-layout>
    @php $proyecto = app('tenancy.proyecto_activo'); @endphp

    <x-slot name="header">
        <x-ui.page-header
            title="Contactos"
            :subtitle="$proyecto->nombre"
            :back="route('proyectos.dashboard', ['proyecto_id' => $proyecto->id])"
            back-label="← Volver al proyecto" />
    </x-slot>

    <div class="py-6">
        <livewire:contactos.lista-contactos :persona="$persona" />
    </div>
</x-app-layout>
