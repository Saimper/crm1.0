<x-app-layout>
    @php $proyecto = app('tenancy.proyecto_activo'); @endphp

    <x-slot name="header">
        <x-ui.page-header
            title="Vista de trabajo"
            :subtitle="$proyecto->nombre"
            :back="route('proyectos.bandeja', ['proyecto_id' => $proyecto->id])"
            back-label="← Volver a bandeja">
            <x-slot name="actions">
                <span class="text-xs text-ink-500 font-mono">{{ $proyecto->codigo }}</span>
            </x-slot>
        </x-ui.page-header>
    </x-slot>

    <div class="py-6">
        <livewire:casos.vista-de-trabajo :persona="$persona" :caso="$caso ?? null" />
    </div>
</x-app-layout>
