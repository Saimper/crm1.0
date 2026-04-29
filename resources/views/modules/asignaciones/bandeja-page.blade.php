<x-app-layout>
    @php $proyecto = app('tenancy.proyecto_activo'); @endphp

    <x-slot name="header">
        <x-ui.page-header
            title="Bandeja"
            :subtitle="$proyecto->nombre">
            <x-slot name="actions">
                <span class="text-xs text-ink-500 font-mono">{{ $proyecto->codigo }}</span>
            </x-slot>
        </x-ui.page-header>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <livewire:asignaciones.bandeja />
        </div>
    </div>
</x-app-layout>
