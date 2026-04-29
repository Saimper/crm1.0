<x-app-layout>
    @php $proyecto = app('tenancy.proyecto_activo'); @endphp

    <x-slot name="header">
        <x-ui.page-header
            title="Nueva persona"
            :subtitle="$proyecto->nombre"
            :back="route('proyectos.dashboard', ['proyecto_id' => $proyecto->id])"
            back-label="← Volver al proyecto">
            <x-slot name="actions">
                <span class="text-xs text-ink-500 font-mono">{{ $proyecto->codigo }}</span>
            </x-slot>
        </x-ui.page-header>
    </x-slot>

    <div class="py-6">
        <livewire:personas.crear-persona />
    </div>
</x-app-layout>
